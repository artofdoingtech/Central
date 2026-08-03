// NON-RÉGRESSION SOURIS des menus flottants.
//
// Pourquoi cette recette existe : rendre un menu accessible au clavier peut le
// casser à la souris, sans qu'un audit clavier n'y voie rien. Deux pièges réels,
// tous deux rencontrés ici :
//   1. si le bouton se ferme sur `blur`, le `mousedown` sur une option déplace le
//      focus AVANT que le `click` ne parte → la sélection souris ne marche plus ;
//   2. si le menu se ferme au `scroll`, le simple fait de donner le focus à un
//      bouton partiellement visible fait défiler la page — et l'évènement `scroll`
//      arrive APRÈS le `click` → le premier clic ne fait rien.
// Les deux se voient uniquement en pilotant à la souris. D'où ce fichier.
const { launch, login, ok, BASE } = require( './lib.js' );

( async () => {
	const { browser, page, console_, scanPhp } = await launch();
	await login( page );
	let fails = 0;
	const t = ( c, m, x ) => { if ( ! ok( c, m, x ) ) { fails++; } };

	/* --- 1. statussel : changer le statut d'une commande (AJAX réel) --- */
	console.log( '\n--- statussel : sélection à la souris (AJAX réel) ---' );
	await page.goto( `${ BASE }/gestion/orders`, { waitUntil: 'networkidle' } );

	// 🚨 On n'opère QUE sur la commande jetable semée par run.sh (voir
	// scripts/recette-seed-souris.php). Avant, ce scénario prenait la PREMIÈRE
	// commande de la liste — une vraie commande du gérant — et lui appliquait un
	// statut au hasard : « Confirmée » déclenchait un ENVOI TRANSPORTEUR RÉEL, et
	// aucun retour arrière ne rappelle un colis.
	const OID = process.env.AOD_SOURIS_OID || '';
	if ( ! OID ) {
		t( false, 'Commande jetable semée (AOD_SOURIS_OID)', 'variable absente : run.sh n’a pas semé' );
	}
	const widget = OID ? page.locator( `.aod-cd-statussel[data-order="${ OID }"]` ) : page.locator( '.aod-cd-nope' );
	if ( OID && ! ( await widget.count() ) ) {
		t( false, 'La commande jetable est visible dans la liste', `data-order="${ OID }" introuvable` );
	}
	if ( await widget.count() ) {
		const avant = await widget.getAttribute( 'data-value' );
		await widget.locator( '.aod-cd-statussel-btn' ).click();
		await page.waitForTimeout( 250 );
		t( await widget.locator( '.aod-cd-statussel-menu' ).isVisible(), 'Le menu s’ouvre au clic' );

		// Statuts INERTES seulement. À proscrire, et pourquoi :
		//   · processing / completed / on-hold → réduction de stock (WooCommerce +
		//     reduce_pack_components_stock), que le retour arrière ne rend pas ;
		//   · aod-confirmed → RESTE interdit, mais plus pour la raison d'origine.
		//     Il l'était parce que AOD_Shipping::auto_send() en faisait partir un
		//     COLIS RÉEL chez le livreur. Depuis la 47.2, confirmer n'envoie plus
		//     rien (le hook n'existe plus, l'envoi est un clic sur « Envoyer ») —
		//     ce danger-là a disparu. Il reste écarté parce qu'il écrit deux métas
		//     de traçabilité (`_aod_confirmed_by`/`_aod_confirmed_at`) que le
		//     retour arrière ne retire pas : sur une commande jetable c'est sans
		//     conséquence, mais ce scénario ne doit rien laisser derrière lui.
		//     🔎 Le retrait de ce statut de la liste est désormais POSSIBLE : c'est
		//     un choix de recette, plus une contrainte du produit.
		const INTERDITS = [ 'aod-confirmed', 'processing', 'completed', 'on-hold' ];
		const opts = widget.locator( '.aod-cd-statussel-opt' );
		let vise = null;
		for ( let i = 0, nb = await opts.count(); i < nb; i++ ) {
			const o = opts.nth( i );
			const v = await o.getAttribute( 'data-value' );
			const slug = ( await o.getAttribute( 'data-status' ) ) || '';
			if ( v !== avant && ! INTERDITS.includes( slug ) ) { vise = v; break; }
		}

		if ( null === vise ) {
			t( false, 'Un statut inerte est proposé pour le test', 'aucune option hors ' + INTERDITS.join( '/' ) );
		} else {
			await widget.locator( `.aod-cd-statussel-opt[data-value="${ vise }"]` ).click();
			await page.waitForTimeout( 1200 ); // laisse passer l'AJAX

			const apres = await widget.getAttribute( 'data-value' );
			t( apres === vise, `Le clic applique le statut (${ avant } → ${ vise })`, `data-value = ${ apres }` );
			t( ! ( await widget.locator( '.aod-cd-statussel-menu' ).isVisible() ), 'Le menu se referme après la sélection' );

			// Retour à l'état initial de la commande JETABLE (elle sera purgée ensuite).
			await widget.locator( '.aod-cd-statussel-btn' ).click();
			await page.waitForTimeout( 200 );
			await widget.locator( `.aod-cd-statussel-opt[data-value="${ avant }"]` ).click();
			await page.waitForTimeout( 1200 );
			t( ( await widget.getAttribute( 'data-value' ) ) === avant, 'Statut d’origine restauré' );
		}
	}

	/* --- 2. csel : choisir un transporteur --- */
	console.log( '\n--- csel : sélection à la souris ---' );
	await page.goto( `${ BASE }/gestion/shipping`, { waitUntil: 'networkidle' } );
	await page.evaluate( () => document.querySelectorAll( 'details.aod-cd-acc' ).forEach( ( d ) => { d.open = true; } ) );
	await page.waitForTimeout( 300 );

	// Gardes de présence : sans elles, un clone sans transporteur configuré faisait
	// lever un timeout Playwright brut — une trace cryptique au lieu d'un message
	// de recette. (Le scénario clavier posait déjà cette garde, pas celui-ci.)
	const cselBtn = page.locator( '.aod-cd-csel-btn:visible' ).first();
	if ( ! ( await cselBtn.count() ) ) {
		t( false, 'Un sélecteur de transporteur est présent', 'aucun .aod-cd-csel-btn visible' );
		console.log( '  (bloc csel non joué)' );
	} else {
	await cselBtn.click();
	await page.waitForTimeout( 250 );
	const menu = page.locator( '.aod-cd-csel-menu:not([hidden])' );
	t( ( await menu.count() ) === 1, 'Le menu s’ouvre au clic' );

	const nbOpts = await menu.locator( '.aod-cd-csel-opt' ).count();
	if ( nbOpts < 3 ) {
		t( false, 'Le menu transporteur propose au moins 3 options', `${ nbOpts } option(s)` );
	}
	const opt = menu.locator( '.aod-cd-csel-opt' ).nth( Math.min( 2, Math.max( 0, nbOpts - 1 ) ) );
	const val = await opt.getAttribute( 'data-value' );
	const lbl = ( await opt.textContent() ).trim();
	await opt.click();
	await page.waitForTimeout( 250 );

	const etat = await page.evaluate( () => {
		const s = document.querySelector( 'select[data-aod-select]' );
		const b = document.querySelector( '.aod-cd-csel-btn' );
		return { value: s.value, label: b.querySelector( '.aod-cd-csel-text' ).textContent.trim() };
	} );
	t( etat.value === val, 'Le clic écrit bien la valeur dans le <select> sous-jacent', `select.value = ${ etat.value }` );
	t( etat.label === lbl, 'Le libellé du bouton suit la sélection', `« ${ etat.label } »` );
	t( ( await page.locator( '.aod-cd-csel-menu:not([hidden])' ).count() ) === 0, 'Le menu se referme après la sélection' );
	}

	/* --- 3. catdd : cocher une catégorie --- */
	console.log( '\n--- catdd : cases à cocher à la souris ---' );
	await page.goto( `${ BASE }/gestion/products?action=new`, { waitUntil: 'networkidle' } );
	await page.evaluate( () => document.querySelectorAll( 'details.aod-cd-acc' ).forEach( ( d ) => { d.open = true; } ) );
	await page.waitForTimeout( 250 );
	const cbtn = page.locator( '.aod-cd-catdd-btn' ).first();
	// 🚨 L'absence du BOUTON n'est jamais légitime : `.aod-cd-catdd-btn` est rendu
	// SANS condition par le formulaire produit (class-aod-cd-section-products.php:491,
	// hors du `if ( $categories )` qui ne gouverne que le contenu du panneau). Une
	// boutique sans catégorie l'affiche quand même, avec « Aucune catégorie » dedans.
	// S'il manque, le formulaire est cassé. a11y-clavier.js fait déjà ce t(false) sur
	// le même widget ; ce bloc s'escamotait en silence, tiers du contrôle souris en
	// moins sur un clone sans catégorie, sans une ligne de rouge.
	if ( ! ( await cbtn.count() ) ) {
		t( false, 'Le sélecteur de catégories est présent sur le formulaire produit',
			'aucun .aod-cd-catdd-btn — il est pourtant rendu sans condition' );
	} else {
		await cbtn.click();
		await page.waitForTimeout( 200 );
		const cb = page.locator( '.aod-cd-catdd-opt input[type=checkbox]' ).first();
		if ( ! ( await cb.count() ) ) {
			// Là, l'absence EST légitime : sans catégorie, le panneau ne montre que
			// « Aucune catégorie » (else du même if PHP, :505). On le PUBLIE plutôt
			// que de fondre ce cas dans le vert (règle du témoin, DoD §C.2).
			t( await page.locator( '.aod-cd-catdd-empty' ).first().count() > 0,
				'⏭️ Aucune catégorie sur ce clone : le panneau montre bien « Aucune catégorie » (case non éprouvée)',
				'ni case à cocher ni message vide — le panneau serait cassé' );
		} else {
			await cb.check();
			await page.waitForTimeout( 200 );
			t( await cb.isChecked(), 'La case se coche' );
			const txt = ( await page.locator( '.aod-cd-catdd-text' ).first().textContent() ).trim();
			const cls = await page.locator( '.aod-cd-catdd-text' ).first().getAttribute( 'class' );
			t( txt.length > 0 && ! cls.includes( 'is-placeholder' ), 'Le libellé du bouton reflète la sélection', `« ${ txt } »` );
			t( await page.locator( '.aod-cd-catdd-panel' ).first().isVisible(),
				'Le panneau RESTE ouvert pendant qu’on coche (il ne se referme pas au focus)' );
		}
	}

	const php = scanPhp( await page.content() );
	t( php.length === 0, 'Aucune erreur PHP', php.join( ' | ' ) );
	t( console_.length === 0, 'Aucune erreur console', console_.join( ' | ' ) );

	console.log( `\n=== SOURIS : ${ fails } échec(s) ===` );
	await browser.close();
	process.exit( fails ? 1 : 0 );
} )();
