// Recette — LE CATALOGUE BILINGUE FR ⇄ AR, DE BOUT EN BOUT (clôture Phase 7).
//
// Les étapes 7.2→7.5 ont rendu bilingue tout le contenu marchand (titre,
// description, arguments, variantes, catégories). Chacune a été recettée à sa
// livraison — ponctuellement. Ce script rend la preuve REJOUABLE PAR CLONE :
// en visiteur réel, il vérifie les surfaces acheteur dans les deux langues, le
// repli d'un produit non traduit, et — le cœur — que RIEN d'arabe ne fuit vers
// les commandes, /gestion ou la base (le FR canonique est une clé serveur).
//
// Fixtures : seedées par `run.sh` via /scripts/recette-seed-bilingue.php, IDs
// reçus en env (AOD_BIL_CAT / TRAD / TEMOIN / LAND). Une fixture absente est un
// ÉCHEC, pas un retrait : contrairement à la landing (optionnelle par produit),
// le bilinguisme du catalogue n'est pas optionnel. Les valeurs arabes sont
// discriminantes (hors dictionnaire de variantes, hors .po) : si elles
// s'affichent, elles ne peuvent venir que de la saisie du gérant.
//
// Exclusions assumées, documentées (limites de la phase — ne pas les « corriger ») :
//   · liste de pack : appel explicite déjà tenu par les tests unitaires 7.2 —
//     la seeder exigerait une offre pack multi-composants, fragile ;
//   · JSON-LD, attributs alt, slugs/URLs : restent FR (ARCHITECTURE §7) — le
//     scan « zéro arabe côté FR » porte sur le TEXTE VISIBLE, qui les exclut ;
//   · le switcher de langue affiche « العربية » sur le site FR : c'est son rôle —
//     il est retiré du texte scanné (.aod-lang).
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );
const { launch, login, ok, BASE, scanPhp, PURGE_VERROUS_COD } = require( './lib' );

const ROOT = path.resolve( __dirname, '..', '..' );
let fails = 0;
const check = ( c, m, extra ) => { if ( ! ok( c, m, extra ) ) { fails++; } };

/** WP-CLI dans le conteneur du clone (jamais `docker exec` global : TEMPLATE.md §3). */
function wp( args ) {
	return execFileSync(
		'docker',
		[ 'compose', 'exec', '-T', 'wpcli', 'wp', ...args ],
		{ cwd: ROOT, encoding: 'utf8' }
	).trim();
}

/* ── Les fixtures : mêmes valeurs que le seed. Une dérive seed/script = rouge. ── */
const FIX = {
	cat: { fr: 'Recette Rayon Bilingue', ar: 'رف المعاينة الثنائية' },
	trad: {
		fr: 'Recette Produit Bilingue',
		ar: 'منتج المعاينة الثنائي',
		descAr: 'وصف المعاينة الثنائية بالعربية.',
		descFr: 'Description française de la recette bilingue.',
		pointsAr: [ 'حجة المعاينة الأولى', 'حجة المعاينة الثانية' ],
		pointFrFallback: 'Argument recette trois', // 3ᵉ puce : pas d'AR → repli FR (le bug 7.3).
		optLabel: { fr: 'Contenance Recette', ar: 'سعة المعاينة' },
		optSaisie: { fr: 'Selva', ar: 'سلفى المعاينة' }, // saisie gérant → AR affiché, value= FR.
		optNue: 'Duna',                                   // ni saisie ni dico → reste FR partout.
	},
	temoin: { fr: 'Recette Produit Temoin', descFr: 'Description française du produit témoin.' },
	land: { ar: 'منتج معاينة الصفحة البيعية' },
};

const ARABIC = /[؀-ۿ]/;
const CLIENT = 'Recette Bilingue'; // Prénom Nom de la commande de garde.

/**
 * Supprime la commande de garde par son TÉLÉPHONE (unique à chaque exécution).
 * Plus sûr que le nom du client : ciblé, et insensible à un homonyme réel.
 * Local uniquement, comme la purge d'amorce.
 */
function supprimerCommande( phone ) {
	if ( ! /^https?:\/\/(localhost|127\.0\.0\.1)(:|\/|$)/.test( BASE ) ) { return; }
	wp( [ 'eval', `foreach ( wc_get_orders( array( 'limit' => -1, 'status' => 'any', 'billing_phone' => '${ phone }' ) ) as $o ) { $o->delete( true ); }` ] );
}

const IDS = {
	cat: parseInt( process.env.AOD_BIL_CAT || '0', 10 ),
	trad: parseInt( process.env.AOD_BIL_TRAD || '0', 10 ),
	temoin: parseInt( process.env.AOD_BIL_TEMOIN || '0', 10 ),
	land: parseInt( process.env.AOD_BIL_LAND || '0', 10 ),
};

/**
 * Texte VISIBLE d'une page, hors switcher de langue (.aod-lang, qui porte
 * « العربية » par construction) et hors symbole monétaire : la boutique vend en
 * DZD et « د.ج » EST de l'écriture arabe, sur le site FR aussi, depuis toujours —
 * ce n'est pas une fuite de la Phase 7. innerText exclut déjà <script>, <style>
 * (JSON-LD) et les attributs (alt, value).
 */
async function visibleText( page ) {
	return page.evaluate( () => {
		const clone = document.body.cloneNode( true );
		clone.querySelectorAll( '.aod-lang, .woocommerce-Price-currencySymbol, script, style, noscript' ).forEach( ( n ) => n.remove() );
		document.body.appendChild( clone );
		clone.style.position = 'absolute';
		clone.style.left = '-99999px';
		const text = clone.innerText;
		clone.remove();
		return text;
	} );
}

/** Charge une page et rend { html, text } après scan PHP. */
async function grab( page, url, label ) {
	await page.goto( url, { waitUntil: 'domcontentloaded' } );
	const html = await page.content();
	check( scanPhp( html ).length === 0, `Aucune erreur PHP servie — ${ label }` );
	return { html, text: await visibleText( page ) };
}

( async () => {
	console.log( `\n▶ Catalogue bilingue FR ⇄ AR — ${ BASE }\n` );

	/* ── 0. Fixtures : présentes, sinon ÉCHEC (jamais de vert par défaut) ── */
	if ( ! IDS.cat || ! IDS.trad || ! IDS.temoin || ! IDS.land ) {
		console.error( '❌ Fixtures manquantes (AOD_BIL_CAT/TRAD/TEMOIN/LAND absents).' );
		console.error( '   Lancer via run.sh, qui seed puis nettoie : bash tools/recette/run.sh bilingue' );
		process.exit( 1 );
	}
	const raw = wp( [ 'eval', `
		$t = get_term( ${ IDS.cat }, 'product_cat' );
		$p = wc_get_product( ${ IDS.trad } ); $w = wc_get_product( ${ IDS.temoin } ); $l = wc_get_product( ${ IDS.land } );
		if ( ! $t || is_wp_error( $t ) || ! $p || ! $w || ! $l ) { echo 'ABSENTES'; }
		else { echo get_term_link( $t ) . '|' . get_permalink( ${ IDS.trad } ) . '|' . get_permalink( ${ IDS.temoin } ) . '|' . get_permalink( ${ IDS.land } ) . '|' . wc_get_page_permalink( 'shop' ) . '|' . home_url( '/' ); }
	` ] );
	if ( raw === 'ABSENTES' ) {
		console.error( '❌ Fixtures introuvables en base : le seed a échoué ou a été purgé trop tôt.' );
		process.exit( 1 );
	}
	const [ catUrl, tradUrl, temoinUrl, landUrl, shopUrl, homeUrl ] = raw.split( '|' );
	check( true, `Fixtures en place (cat #${ IDS.cat }, produits #${ IDS.trad }/#${ IDS.temoin }/#${ IDS.land })` );

	// Le symbole monétaire de la boutique : en DZD c'est « د.ج » — de l'écriture
	// arabe LÉGITIME sur le site FR (prix Woo côté serveur, totaux du formulaire
	// COD côté JS via fmt()). On le retire du texte scanné plutôt que d'énumérer
	// des sélecteurs : robuste quel que soit le thème du clone.
	const CURRENCY = wp( [ 'eval', 'echo html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, "UTF-8" );' ] );
	const noCurrency = ( t ) => ( CURRENCY ? t.split( CURRENCY ).join( '' ) : t );

	// Le terrain : verrous anti-flood des exécutions précédentes + commandes de
	// garde accumulées. Local uniquement (même garde-fou que commande-cod.js).
	if ( /^https?:\/\/(localhost|127\.0\.0\.1)(:|\/|$)/.test( BASE ) ) {
		wp( [ 'eval', PURGE_VERROUS_COD ] ); // Ciblé : plus de `transient delete --all`. Cf. lib.js.
		wp( [ 'eval', `foreach ( wc_get_orders( array( 'limit' => -1, 'status' => 'any' ) ) as $o ) { if ( trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ) === '${ CLIENT }' ) { $o->delete( true ); } }` ] );
	}

	/* ══ 1. Visiteur, site en FRANÇAIS : zéro arabe visible ══ */
	console.log( '\n— Site en FRANÇAIS (le sens que le bug 7.3 avait trahi) —' );
	{
		const { browser, page } = await launch( { state: false } );
		for ( const [ url, label ] of [ [ homeUrl, 'accueil' ], [ shopUrl, 'Boutique' ], [ tradUrl, 'fiche produit traduit' ] ] ) {
			const { text } = await grab( page, url, `${ label } (FR)` );
			const scanned = noCurrency( text );
			const hit = ( scanned.match( new RegExp( '[\\u0600-\\u06FF][^\\n]*', 'g' ) ) || [] )[ 0 ] || '';
			check( ! ARABIC.test( scanned ), `Zéro caractère arabe visible — ${ label } (FR)`, hit );
		}
		const { text } = await grab( page, tradUrl, 'fiche traduit (FR, valeurs)' );
		check( text.includes( FIX.trad.fr ), 'Le titre FR s\'affiche sur la fiche (FR)' );
		check( text.includes( FIX.trad.optLabel.fr ) && text.includes( FIX.trad.optSaisie.fr ), 'Variantes en FR sur la fiche (FR)' );
		await browser.close();
	}

	/* ══ 2. Visiteur, site en ARABE : les 10 surfaces ══ */
	console.log( '\n— Site en ARABE (les surfaces 7.2→7.5, plus le héro landing) —' );
	{
		const { browser, ctx, page } = await launch( { state: false } );
		await ctx.addCookies( [ { name: 'aod_lang', value: 'ar', url: BASE } ] );

		// Surface 1+2 : accueil — grille produits et grille catégories.
		const home = await grab( page, homeUrl, 'accueil (AR)' );
		check( home.html.includes( 'lang="ar' ) && / dir="rtl"|dir="rtl" /.test( home.html ), '<html> en arabe et RTL — accueil' );
		check( home.text.includes( FIX.trad.ar ), 'Surface 1 — grille produits de l\'accueil : titre AR' );
		check( home.text.includes( FIX.cat.ar ), 'Surface 2 — grille « Nos catégories » : nom AR (le get_term de la 7.5)' );
		check( ! home.text.includes( FIX.trad.fr ) && ! home.text.includes( FIX.cat.fr ), 'Accueil AR : aucune valeur FR des fixtures en clair' );

		// Surface 3 : Boutique.
		const shop = await grab( page, shopUrl, 'Boutique (AR)' );
		check( shop.text.includes( FIX.trad.ar ), 'Surface 3 — grille Boutique : titre AR' );

		// Surface 4 : archive de catégorie — <h1> ET <title> (redondance single_term_title prouvée en 7.5).
		const cat = await grab( page, catUrl, 'archive catégorie (AR)' );
		const catTitle = await page.title();
		check( cat.text.includes( FIX.cat.ar ), 'Surface 4a — <h1> d\'archive : nom de catégorie AR' );
		check( catTitle.includes( FIX.cat.ar ), 'Surface 4b — <title> d\'archive : nom de catégorie AR', catTitle );

		// Surfaces 5-9 : LA fiche du produit traduit.
		const fiche = await grab( page, tradUrl, 'fiche produit traduit (AR)' );
		const ficheTitle = await page.title();
		check( fiche.text.includes( FIX.trad.ar ), 'Surface 5a — <h1> de la fiche : titre AR' );
		check( ficheTitle.includes( FIX.trad.ar ), 'Surface 5b — <title> du document : titre AR (single_post_title)', ficheTitle );
		check( fiche.html.includes( `<p>${ FIX.trad.descAr }</p>` ), 'Surface 6 — description AR, ENVELOPPÉE de <p> (la priorité 9 avant wpautop)' );
		check( FIX.trad.pointsAr.every( ( p ) => fiche.text.includes( p ) ), 'Surface 7a — arguments de vente : les 2 puces AR saisies' );
		check( fiche.text.includes( FIX.trad.pointFrFallback ), 'Surface 7b — la 3ᵉ puce (sans AR) replie en FR, au bon rang' );
		check( fiche.text.includes( FIX.trad.optLabel.ar ), 'Surface 8a — libellé de section de variantes : AR saisi' );
		check( fiche.text.includes( FIX.trad.optSaisie.ar ), 'Surface 8b — valeur de variante : AR saisi (hors dictionnaire)' );
		check( fiche.text.includes( FIX.trad.optNue ), 'Surface 8c — valeur sans AR ni dictionnaire : reste FR (cascade 7.4)' );
		check( await page.locator( `.aod-cod__form input[value="${ FIX.trad.optSaisie.fr }"]` ).count() > 0, 'Surface 8d — value= du DOM : le nom FR canonique (la CLÉ, jamais traduite)' );
		const crumb = page.locator( '.woocommerce-breadcrumb, .ast-breadcrumbs' );
		check( await crumb.count() > 0, 'Surface 9 — le fil d\'ariane existe (garde-fou : 0 élément = échec)' );
		if ( await crumb.count() ) {
			const t = ( await crumb.first().innerText() ) || '';
			check( t.includes( FIX.cat.ar ), 'Surface 9a — fil d\'ariane : catégorie en AR', t.replace( /\s+/g, ' ' ) );
			check( t.includes( FIX.trad.ar ), 'Surface 9b — fil d\'ariane : titre produit en AR' );
		}
		check( ! fiche.text.includes( FIX.trad.fr ) && ! fiche.text.includes( FIX.trad.descFr ), 'Fiche AR : ni le titre ni la description FR en clair' );

		// Surface 10 : héro de la landing — l'appel EXPLICITE (hors filtres) de la 7.2.
		const land = await grab( page, landUrl, 'landing (AR)' );
		const hero = page.locator( '.aod-landing__hero-title' );
		check( await hero.count() > 0, 'Surface 10 — le héro de la landing existe (garde-fou : 0 élément = échec)' );
		if ( await hero.count() ) {
			check( ( ( await hero.first().innerText() ) || '' ).includes( FIX.land.ar ), 'Surface 10 — héro sans titre saisi : repli sur le NOM AR du produit' );
		}
		void land;

		// Le témoin : un produit jamais traduit, site en arabe → ses valeurs FR (repli), page RTL saine.
		const temoin = await grab( page, temoinUrl, 'fiche produit témoin (AR)' );
		check( temoin.text.includes( FIX.temoin.fr ) && temoin.text.includes( FIX.temoin.descFr ), 'Témoin non traduit en AR : titre et description FR affichés (repli intégral)' );

		// Mobile 390 px : la fiche AR, le viewport de référence du template.
		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( tradUrl, { waitUntil: 'domcontentloaded' } );
		const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		check( overflow <= 1, 'Fiche AR à 390 px : aucun débordement horizontal', `dépassement : ${ overflow }px` );

		await browser.close();
	}

	/* ══ 3. LA GARDE : rien d'arabe ne fuit vers la commande, /gestion, la base ══ */
	console.log( '\n— La garde de portée (le cœur de la clôture) —' );

	// 3a. Commande COD réelle, site en ARABE, variante choisie sur son libellé arabe.
	const turnstile = wp( [ 'eval', `echo ( class_exists( 'AOD_COD_Turnstile' ) && AOD_COD_Turnstile::instance()->is_enabled() ) ? 'on' : 'off';` ] );
	if ( turnstile === 'on' ) {
		// Pas un skip silencieux : on le dit, et la garde reste prouvée par 3b/3c.
		console.log( '  ⚠️  Turnstile actif : la commande de garde ne peut pas être passée sans jeton.' );
		console.log( '     Désactive-le le temps de la recette pour la preuve complète (cf. commande-cod.js).' );
	} else {
		const { browser, ctx, page } = await launch( { state: false } );
		await ctx.addCookies( [ { name: 'aod_lang', value: 'ar', url: BASE } ] );
		await page.goto( tradUrl, { waitUntil: 'domcontentloaded' } );

		// La variante par son AFFICHAGE arabe — exactement le geste d'un acheteur arabophone.
		const card = page.locator( `.aod-cod__form label:has-text("${ FIX.trad.optSaisie.ar }")` );
		check( await card.count() > 0, 'La variante est cliquable par son libellé ARABE' );
		if ( await card.count() ) { await card.first().click(); }

		const phone = `05${ String( Date.now() ).slice( -8 ) }`; // Unique : le verrou 24 h est par numéro.
		await page.fill( '.aod-cod__form input[name="name"]', CLIENT );
		await page.fill( '.aod-cod__form input[name="phone"]', phone );
		await page.locator( '.aod-cod__form select[name="wilaya"]' ).selectOption( '16' );
		await page.waitForFunction( () => document.querySelector( '.aod-cod__form select[name="commune"]' )?.options.length > 1 );
		await page.locator( '.aod-cod__form select[name="commune"]' ).selectOption( { index: 1 } );
		const address = page.locator( '.aod-cod__form input[name="address"], .aod-cod__form textarea[name="address"]' );
		if ( await address.count() ) { await address.first().fill( 'Rue de la recette, 16000' ); }
		await page.waitForTimeout( 3500 ); // Le time-trap est une protection, pas un obstacle.
		await page.click( '.aod-cod__submit' );
		await page.waitForFunction(
			() => document.querySelector( '.aod-cod__done' ) || ( document.querySelector( '.aod-cod__msg' )?.textContent || '' ).trim().length > 0,
			null, { timeout: 15000 }
		);
		const done = await page.locator( '.aod-cod__done' ).count() > 0;
		check( done, 'Une commande COD passe, site en arabe, variante arabe choisie' );
		await browser.close();

		if ( done ) {
			// Relecture HPOS : ce que les e-mails, WhatsApp et bordereaux REJOUERONT.
			const dump = wp( [ 'eval', `
				$os = wc_get_orders( array( 'limit' => 1, 'status' => 'any', 'billing_phone' => '${ phone }' ) );
				if ( ! $os ) { echo 'ABSENTE'; }
				else {
					$items = $os[0]->get_items();
					$item  = reset( $items );
					$metas = array();
					foreach ( $item->get_meta_data() as $m ) { $d = $m->get_data(); $metas[] = $d['key'] . '=' . $d['value']; }
					echo $item->get_name() . '|' . implode( ';', $metas );
				}
			` ] );
			if ( dump === 'ABSENTE' ) {
				check( false, 'La commande de garde est introuvable en base' );
			} else {
				const [ itemName, metas ] = dump.split( '|' );
				check( itemName === FIX.trad.fr, 'order_item_name = le titre FR CANONIQUE (jamais l\'arabe)', itemName );
				// La clé est le libellé FR BRUT (class-aod-cod-form.php:1130,1168) — le
				// format « [Couleur] » vu en 7.4 est celui de la branche de compat mono-couleur.
				check( metas.includes( `${ FIX.trad.optLabel.fr }=${ FIX.trad.optSaisie.fr }` ), 'Meta de variante : clé FR = valeur FR (la preuve 7.4)', metas );
				check( ! ARABIC.test( itemName + metas ), 'Aucun caractère arabe dans la ligne de commande', metas );
			}
			// 🚨 Supprimer la commande de garde MAINTENANT, repérée par son téléphone
			// (unique à chaque exécution). La porte ne purgeait qu'à son DÉBUT : la
			// commande qu'elle venait de créer survivait donc jusqu'à l'exécution
			// suivante — au statut `processing`, donc COMPTÉE DANS LE CA affiché au
			// gérant. Une recette ne doit pas fausser les chiffres qu'elle observe.
			supprimerCommande( phone );
		}
	}

	// 3b. /gestion en ARABE : le gérant voit le FR canonique (garde `aod_cd`).
	{
		const { browser, ctx, page } = await launch( { state: false } );
		await login( page );
		await ctx.addCookies( [ { name: 'aod_lang', value: 'ar', url: BASE } ] );
		await page.goto( `${ BASE }/gestion/products`, { waitUntil: 'domcontentloaded' } );
		check( await page.locator( 'body.aod-cd' ).count() > 0, '/gestion répond (garde-fou : racine body.aod-cd présente)' );
		const prods = await visibleText( page );
		check( prods.includes( FIX.trad.fr ), '/gestion Produits en AR : le titre reste FRANÇAIS (le repère du gérant)' );
		check( ! prods.includes( FIX.trad.ar ), '/gestion Produits en AR : jamais le titre arabe' );
		await page.goto( `${ BASE }/gestion/categories`, { waitUntil: 'domcontentloaded' } );
		const cats = await visibleText( page );
		check( cats.includes( FIX.cat.fr ), '/gestion Catégories en AR : le nom reste FRANÇAIS' );
		// (le nom AR vit dans la VALEUR de l'input de saisie — invisible d'innerText : c'est le champ 7.5, pas une fuite)
		await browser.close();
	}

	// 3c. SQL brut : la base n'a jamais bougé (invariant n°3 de la phase).
	const sql = wp( [ 'eval', `
		global $wpdb;
		echo $wpdb->get_var( $wpdb->prepare( 'SELECT post_title FROM ' . $wpdb->posts . ' WHERE ID = %d', ${ IDS.trad } ) )
			. '|' . $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . $wpdb->terms . ' WHERE term_id = %d', ${ IDS.cat } ) );
	` ] );
	const [ sqlTitle, sqlCat ] = sql.split( '|' );
	check( sqlTitle === FIX.trad.fr && sqlCat === FIX.cat.fr, 'SQL brut : post_title et wp_terms.name restent le FR canonique', sql );

	console.log( fails ? `\n❌ ${ fails } échec(s).\n` : '\n✅ Le catalogue bilingue tient — Phase 7 prouvée sur ce clone.\n' );
	process.exit( fails ? 1 : 0 );
} )().catch( ( e ) => {
	console.error( '\n💥 ' + e.message + '\n' );
	process.exit( 1 );
} );
