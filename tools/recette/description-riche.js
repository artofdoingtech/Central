// Recette — LA DESCRIPTION PRODUIT RICHE, FR ⇄ AR (clôture Phase 8).
//
// Les étapes 8.1→8.3 ont donné au gérant un éditeur riche (titres, listes),
// l'insertion de PHOTOS, et la VIDÉO (YouTube en façade + fichier local natif),
// sur le couple description { fr, ar }. Chacune a été recettée à sa livraison —
// ponctuellement. Ce script rend la preuve REJOUABLE PAR CLONE : en visiteur
// réel, une description riche s'affiche correctement dans les deux langues et à
// 390 px ; un produit à description PLATE est inchangé ; et rien de la description
// ne fuit vers la commande (elle n'y entre pas — on le re-prouve).
//
// Fixtures : seedées par `run.sh` via /scripts/recette-seed-phase8.php, IDs reçus
// en env (AOD_P8_RICH / PLAIN / IMG / VID). Une fixture absente est un ÉCHEC, pas
// un retrait : la description riche n'est pas « optionnelle ». Valeurs AR
// discriminantes (hors .po / dictionnaire) : si elles s'affichent, elles viennent
// de la saisie.
//
// Exclusions assumées, documentées (limites de la phase — ne pas les « corriger ») :
//   · JSON-LD, meta description, alt d'image, aperçu REST : restent FR
//     (ARCHITECTURE §7) — le scan porte sur le TEXTE VISIBLE (innerText), qui les
//     exclut par construction ;
//   · le switcher de langue (« العربية » sur le site FR) : retiré du texte scanné.
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
	rich: {
		name: 'Recette Description Riche',
		h2Fr: 'Section Recette Riche',
		liFr: 'Bienfait recette alpha',
		h2Ar: 'قسم المعاينة الغني',
		liAr: 'فائدة المعاينة ألفا',
	},
	plain: { name: 'Recette Description Plate', descFr: 'Description plate sans aucune mise en forme.' },
	ytId: 'dQw4w9WgXcQ',
};

const ARABIC = /[؀-ۿ]/;
const CLIENT = 'Recette Riche'; // Prénom Nom de la commande de garde.

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
	rich: parseInt( process.env.AOD_P8_RICH || '0', 10 ),
	plain: parseInt( process.env.AOD_P8_PLAIN || '0', 10 ),
	img: parseInt( process.env.AOD_P8_IMG || '0', 10 ),
	vid: parseInt( process.env.AOD_P8_VID || '0', 10 ),
};

/** Texte VISIBLE d'une page (innerText), hors switcher de langue et symbole monétaire. */
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

/** L'onglet Description de WooCommerce est masqué tant que son lien n'est pas actif :
 *  on le clique pour rendre son panneau visible (sinon innerText l'exclut). */
async function openDescription( page ) {
	const tab = page.locator( '.wc-tabs .description_tab a, li.description_tab a, #tab-title-description a' ).first();
	if ( await tab.count() ) {
		try { await tab.click(); } catch ( e ) { /* déjà actif / pas de JS d'onglets */ }
	}
}

/** Charge une page, scanne le PHP, ouvre l'onglet Description. */
async function grab( page, url, label ) {
	await page.goto( url, { waitUntil: 'domcontentloaded' } );
	const html = await page.content();
	check( scanPhp( html ).length === 0, `Aucune erreur PHP servie — ${ label }` );
	await openDescription( page );
	return html;
}

( async () => {
	console.log( `\n▶ Description produit riche FR ⇄ AR — ${ BASE }\n` );

	/* ── 0. Fixtures : présentes, sinon ÉCHEC (jamais de vert par défaut) ── */
	if ( ! IDS.rich || ! IDS.plain || ! IDS.img || ! IDS.vid ) {
		console.error( '❌ Fixtures manquantes (AOD_P8_RICH/PLAIN/IMG/VID absents).' );
		console.error( '   Lancer via run.sh, qui seed puis nettoie : bash tools/recette/run.sh riche' );
		process.exit( 1 );
	}
	const raw = wp( [ 'eval', `
		$r = wc_get_product( ${ IDS.rich } ); $p = wc_get_product( ${ IDS.plain } );
		if ( ! $r || ! $p ) { echo 'ABSENTES'; }
		else { echo get_permalink( ${ IDS.rich } ) . '|' . get_permalink( ${ IDS.plain } ); }
	` ] );
	if ( raw === 'ABSENTES' ) {
		console.error( '❌ Fixtures introuvables en base : le seed a échoué ou a été purgé trop tôt.' );
		process.exit( 1 );
	}
	const [ richUrl, plainUrl ] = raw.split( '|' );
	check( true, `Fixtures en place (riche #${ IDS.rich }, plate #${ IDS.plain }, img #${ IDS.img }, vid #${ IDS.vid })` );

	// Terrain propre : verrous anti-flood + commandes de garde accumulées (local only).
	if ( /^https?:\/\/(localhost|127\.0\.0\.1)(:|\/|$)/.test( BASE ) ) {
		wp( [ 'eval', PURGE_VERROUS_COD ] ); // Ciblé : plus de `transient delete --all`. Cf. lib.js.
		wp( [ 'eval', `foreach ( wc_get_orders( array( 'limit' => -1, 'status' => 'any' ) ) as $o ) { if ( trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ) === '${ CLIENT }' ) { $o->delete( true ); } }` ] );
	}

	/* ══ 1. Le produit riche, site en FRANÇAIS ══ */
	console.log( '\n— Fiche riche en FRANÇAIS (titres, listes, image, façade, vidéo-fichier) —' );
	{
		const { browser, page } = await launch( { state: false } );

		// Traçage réseau : aucun domaine YouTube ni le MP4 ne doit être requêté au
		// chargement (façade = 0 requête avant clic ; vidéo-fichier = preload none).
		const ytReqs = [];
		const vidReqs = [];
		page.on( 'request', ( req ) => {
			const u = req.url();
			if ( /youtube\.com|youtu\.be|youtube-nocookie\.com|ytimg\.com|googlevideo\.com/.test( u ) ) { ytReqs.push( u ); }
			if ( u.includes( `recette-phase8-` ) && /\.mp4(\?|$)/.test( u ) ) { vidReqs.push( u ); }
		} );

		await grab( page, richUrl, 'fiche riche (FR)' );
		await page.waitForLoadState( 'networkidle' ).catch( () => {} );

		const t = await visibleText( page );
		check( t.includes( FIX.rich.h2Fr ), 'FR — le <h2> de la description riche est rendu (texte visible)', FIX.rich.h2Fr );
		check( t.includes( FIX.rich.liFr ), 'FR — les puces de liste sont rendues', FIX.rich.liFr );

		// L'image, avec son srcset RÉGÉNÉRÉ au rendu (wp_filter_content_tags, prio 12).
		const img = page.locator( `img.wp-image-${ IDS.img }` ).first();
		check( await img.count() > 0, 'FR — l\'<img> de la description est présente (garde-fou : 0 = échec)' );
		if ( await img.count() ) {
			const srcset = await img.getAttribute( 'srcset' );
			check( !! srcset && srcset.length > 0, 'FR — srcset régénéré sur l\'image (stocké sans, enrichi au rendu — 8.2)', String( srcset ) );
		}

		// La façade YouTube : présente, et AUCUN iframe / requête tierce avant clic.
		const facade = page.locator( '.aod-landing__video-facade' );
		check( await facade.count() > 0, 'FR — la façade YouTube est présente (garde-fou : 0 = échec)' );
		check( await page.locator( 'iframe[src*="youtube"]' ).count() === 0, 'FR — 0 <iframe> YouTube avant le clic' );
		check( ytReqs.length === 0, 'FR — 0 requête vers un domaine YouTube au chargement', ytReqs.join( ' ' ) );

		// La vidéo-fichier native : présente, preload none (0 octet), src absolue, HTTP 200.
		const vid = page.locator( 'video.aod-desc-video-file' ).first();
		check( await vid.count() > 0, 'FR — le <video> fichier natif est présent (garde-fou : 0 = échec)' );
		if ( await vid.count() ) {
			const vsrc = ( await vid.getAttribute( 'src' ) ) || '';
			check( /^https?:\/\//.test( vsrc ), 'FR — src du <video> ABSOLUE (jamais relative fragile — correctif 8.3)', vsrc );
			check( ( await vid.getAttribute( 'preload' ) ) === 'none', 'FR — <video preload="none"> (0 octet chargé avant lecture)' );
			check( vidReqs.length === 0, 'FR — le MP4 n\'est PAS requêté au chargement (preload none tenu)', vidReqs.join( ' ' ) );
			const resp = await page.request.get( vsrc );
			check( resp.status() === 200, 'FR — le fichier vidéo répond HTTP 200', String( resp.status() ) );
		}

		// Le clic sur la façade → l'iframe nocookie apparaît (et SEULEMENT alors).
		if ( await facade.count() ) {
			await facade.first().click();
			const frame = page.locator( 'iframe[src*="youtube-nocookie.com/embed/"]' );
			await frame.first().waitFor( { timeout: 6000 } ).catch( () => {} );
			check( await frame.count() > 0, 'FR — au clic : une iframe youtube-nocookie/embed apparaît' );
			if ( await frame.count() ) {
				const src = ( await frame.first().getAttribute( 'src' ) ) || '';
				check( src.includes( `/embed/${ FIX.ytId }` ), 'FR — l\'iframe pointe le bon ID (embed nocookie)', src );
			}
		}

		await browser.close();
	}

	/* ══ 2. Le produit riche, site en ARABE ══ */
	console.log( '\n— Fiche riche en ARABE (RTL, contenu AR discriminant, 390 px) —' );
	{
		const { browser, ctx, page } = await launch( { state: false } );
		await ctx.addCookies( [ { name: 'aod_lang', value: 'ar', url: BASE } ] );

		const html = await grab( page, richUrl, 'fiche riche (AR)' );
		check( html.includes( 'lang="ar' ) && / dir="rtl"|dir="rtl" /.test( html ), 'AR — <html> en arabe et RTL' );

		const t = await visibleText( page );
		check( t.includes( FIX.rich.h2Ar ), 'AR — le <h2> AR discriminant est rendu (le swap 7.2 sur the_content)', FIX.rich.h2Ar );
		check( t.includes( FIX.rich.liAr ), 'AR — les puces de liste AR sont rendues', FIX.rich.liAr );
		check( ! t.includes( FIX.rich.h2Fr ), 'AR — le <h2> FR n\'a PAS fui dans le texte visible (JSON-LD exclu)', FIX.rich.h2Fr );

		check( await page.locator( `img.wp-image-${ IDS.img }` ).count() > 0, 'AR — l\'image de description est présente' );
		check( await page.locator( '.aod-landing__video-facade' ).count() > 0, 'AR — la façade YouTube est présente' );

		// Mobile 390 px : la fiche riche AR, le viewport de référence du template.
		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( richUrl, { waitUntil: 'domcontentloaded' } );
		await openDescription( page );
		const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		check( overflow <= 1, 'AR — fiche riche à 390 px : aucun débordement horizontal', `dépassement : ${ overflow }px` );

		await browser.close();
	}

	/* ══ 3. Le témoin : description PLATE, identique à l'avant-phase ══ */
	console.log( '\n— Témoin à description PLATE (aucun enrichissement) —' );
	{
		const { browser, ctx, page } = await launch( { state: false } );

		// FR : texte simple, AUCUN élément riche.
		await grab( page, plainUrl, 'témoin plat (FR)' );
		const ft = await visibleText( page );
		check( ft.includes( FIX.plain.descFr ), 'Témoin FR — la description plate s\'affiche' );
		check( await page.locator( '.aod-landing__video-facade' ).count() === 0, 'Témoin FR — aucune façade vidéo' );
		check( await page.locator( 'video.aod-desc-video-file' ).count() === 0, 'Témoin FR — aucune vidéo-fichier' );
		check( await page.locator( `img.wp-image-${ IDS.img }` ).count() === 0, 'Témoin FR — aucune image de description riche' );

		// AR : AR vide → repli FR intégral, page RTL saine.
		await ctx.addCookies( [ { name: 'aod_lang', value: 'ar', url: BASE } ] );
		const htmlAr = await grab( page, plainUrl, 'témoin plat (AR)' );
		check( htmlAr.includes( 'lang="ar' ), 'Témoin AR — page en arabe (RTL)' );
		const at = await visibleText( page );
		check( at.includes( FIX.plain.descFr ), 'Témoin AR — description AR vide → repli FR affiché' );

		await browser.close();
	}

	/* ══ 4. LA GARDE : la description ne fuit pas vers la commande ══ */
	console.log( '\n— La garde : rien de la description n\'entre dans la commande —' );
	const turnstile = wp( [ 'eval', `echo ( class_exists( 'AOD_COD_Turnstile' ) && AOD_COD_Turnstile::instance()->is_enabled() ) ? 'on' : 'off';` ] );
	if ( turnstile === 'on' ) {
		console.log( '  ⚠️  Turnstile actif : la commande de garde ne peut pas être passée sans jeton.' );
		console.log( '     Désactive-le le temps de la recette (cf. commande-cod.js). La garde reste partielle.' );
	} else {
		const { browser, page } = await launch( { state: false } );
		await page.goto( richUrl, { waitUntil: 'domcontentloaded' } );

		const phone = `05${ String( Date.now() ).slice( -8 ) }`; // Unique : verrou 24 h par numéro.
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
		check( done, 'Une commande COD passe sur le produit à description riche' );
		await browser.close();

		if ( done ) {
			// Relecture HPOS : ce que les e-mails et bordereaux REJOUERONT (7.6 D5).
			const dump = wp( [ 'eval', `
				$os = wc_get_orders( array( 'limit' => 1, 'status' => 'any', 'billing_phone' => '${ phone }' ) );
				if ( ! $os ) { echo 'ABSENTE'; }
				else {
					$items = $os[0]->get_items();
					$item  = reset( $items );
					$metas = array();
					foreach ( $item->get_meta_data() as $m ) { $d = $m->get_data(); $metas[] = $d['key'] . '=' . $d['value']; }
					echo $item->get_name() . '||' . implode( ';', $metas );
				}
			` ] );
			if ( dump === 'ABSENTE' ) {
				check( false, 'La commande de garde est introuvable en base' );
			} else {
				const [ itemName, metas ] = dump.split( '||' );
				check( itemName === FIX.rich.name, 'order_item_name = le TITRE du produit (jamais un fragment de description)', itemName );
				check( ! ( itemName + ( metas || '' ) ).includes( FIX.rich.h2Fr ), 'La description riche (le <h2>) N\'ENTRE PAS dans la ligne de commande', metas );
			}

			// 4b. /gestion → Commandes : la page de détail n'affiche pas la
			// description riche. 🚨 CE CONTRÔLE DOIT S'EXÉCUTER TANT QUE LA COMMANDE
			// EXISTE. Il tournait APRÈS `supprimerCommande()` : l'absence du <h2>
			// était alors vraie PAR CONSTRUCTION (plus aucune commande à fuir), donc
			// une vraie régression — /gestion rendant `the_content` du produit —
			// serait restée verte. On adosse l'absence à une PRÉSENCE (le nom du
			// client de garde doit, lui, apparaître), sinon la page vide suffirait.
			{
				const { browser: gb, page: gp } = await launch( { state: false } );
				await login( gp );
				await gp.goto( `${ BASE }/gestion/orders`, { waitUntil: 'domcontentloaded' } );
				check( await gp.locator( 'body.aod-cd' ).count() > 0, '/gestion Commandes répond (garde-fou : racine body.aod-cd)' );
				const gt = await visibleText( gp );
				check( gt.includes( CLIENT ), 'La commande de garde EST bien listée dans /gestion (ancre de présence)', `client « ${ CLIENT }» attendu` );
				check( ! gt.includes( FIX.rich.h2Fr ), '/gestion Commandes : la description riche du produit n\'y apparaît pas' );
				await gb.close();
			}

			// 🚨 Supprimer la commande de garde MAINTENANT, repérée par son téléphone
			// (unique à chaque exécution). La porte ne purgeait qu'à son DÉBUT : la
			// commande qu'elle venait de créer survivait donc jusqu'à l'exécution
			// suivante — au statut `processing`, donc COMPTÉE DANS LE CA affiché au
			// gérant. Une recette ne doit pas fausser les chiffres qu'elle observe.
			supprimerCommande( phone );
		}
	}

	// (Le contrôle « /gestion n'affiche pas la description riche » a été remonté
	// dans le bloc 4, AVANT la suppression de la commande de garde : sinon il
	// passait au vert par absence de commande, pas par absence de fuite.)

	/* ══ 5. L'ÉDITEUR de cette description respire, et il est borné (47.6) ══
	 *
	 * 🚨 POURQUOI ICI, ET POURQUOI AU NAVIGATEUR. Jusqu'à la 47.6, cette porte
	 * n'éprouvait que le RENDU PUBLIC de la description. Le côté gérant — celui
	 * où on l'ÉCRIT — n'était mesuré nulle part : `a11y-clavier` et `a11y-souris`
	 * ouvrent bien le formulaire produit, mais aucune ne regarde TinyMCE.
	 *
	 * Et un test statique ne peut pas remplacer ceci. Vérifier que le fichier JS
	 * contient « wpautoresize » serait VERT avec `wp_autoresize_on` absent —
	 * c'est-à-dire avec le plugin chargé et totalement INERTE (lu dans sa source :
	 * `plugin.js:160`, tout est conditionné à ce drapeau). La hauteur d'une iframe
	 * est un fait de rendu : elle se mesure.
	 *
	 * ⚠️ La porte PROVOQUE l'état au lieu de le décrire : elle injecte du contenu
	 * par `setContent`, auquel le plugin réagit (`plugin.js:181`). Elle ne dépend
	 * donc d'aucune longueur de fixture — un seed qui maigrirait ne la rendrait
	 * pas silencieusement vraie.
	 *
	 * 🔴 RIEN N'EST ENREGISTRÉ : on ne soumet jamais le formulaire. Le produit
	 * témoin ressort intact.
	 */
	{
		// PLAFOND doit rester synchronisé avec `autoresize_max_height` de
		// section-products.js — une dérive porte/code = rouge, exactement comme
		// l'objet FIX ci-dessus.
		// PLANCHER, lui, n'est écrit NULLE PART dans le code : c'est le défaut du
		// plugin, soit la hauteur du <textarea> (`rows="6"`). La porte le mesure
		// donc en tant que RÉSULTAT — si quelqu'un touche `rows`, elle rougit en
		// disant le nouveau chiffre, ce qui est exactement le service attendu.
		const PLANCHER = 132;
		const PLAFOND  = 500;
		const paragraphes = ( n, mot ) => Array.from( { length: n }, ( _, i ) => `<p>${ mot } ${ i } — texte de mesure de hauteur.</p>` ).join( '' );

		/** Hauteur de l'iframe de l'éditeur dont le textarea porte `data-rich-dir`. */
		const hauteur = ( p, dir ) => p.evaluate( ( d ) => {
			const ta = document.querySelector( '.aod-cd-rich[data-rich-dir="' + d + '"]' );
			const box = ta && ta.closest( '.aod-cd-lp-lang' );
			const ifr = box && box.querySelector( 'iframe' );
			return ifr ? Math.round( ifr.getBoundingClientRect().height ) : 0;
		}, dir );

		/** Injecte du contenu DANS l'éditeur de ce sens, puis laisse le plugin réagir. */
		const remplir = async ( p, dir, html ) => {
			await p.evaluate( ( [ d, h ] ) => {
				const ta = document.querySelector( '.aod-cd-rich[data-rich-dir="' + d + '"]' );
				const ed = ta && window.tinymce && window.tinymce.get( ta.id );
				if ( ed ) { ed.setContent( h ); }
			}, [ dir, html ] );
			await p.waitForTimeout( 900 ); // `setcontent` → wait(3, 100) côté plugin.
		};

		for ( const [ langue, dir ] of [ [ 'FRANÇAIS', 'ltr' ], [ 'ARABE', 'rtl' ] ] ) {
			console.log( `\n— Éditeur de description en ${ langue } (47.6) —` );
			const { browser: eb, ctx: ec, page: ep } = await launch( { state: false } );
			try {
				await login( ep );
				if ( 'rtl' === dir ) {
					await ec.addCookies( [ { name: 'aod_lang', value: 'ar', url: BASE } ] );
				}
				await ep.goto( `${ BASE }/gestion/products?action=edit&product=${ IDS.plain }`, { waitUntil: 'networkidle' } );
				await ep.waitForTimeout( 1800 ); // `autoresize_on_init` frappe 10× / 200 ms.

				// Témoin : sans éditeur, TOUTES les comparaisons ci-dessous seraient
				// vraies sur des zéros. Un « 0 trouvé » doit rougir, pas passer.
				const nb = await ep.evaluate( () => document.querySelectorAll( '.aod-cd-rich' ).length );
				check( nb === 2, `${ langue } — les 2 éditeurs riches (FR + AR) sont initialisés (vu : ${ nb })` );

				// 🚨 LE PLANCHER SE MESURE AUSSI, et il a failli manquer. Sans
				// `autoresize_min_height` explicite, le défaut du plugin est
				// inexploitable et l'éditeur s'ouvre COLLÉ AU PLAFOND : 500 px pour une
				// description de 216 caractères (mesuré en écrivant la 47.6). « Toujours
				// 132 » serait devenu « toujours 500 » — toujours aucune respiration.
				// Sans cette ligne, la porte ne rougirait que par ricochet, sans dire
				// pourquoi.
				const h0 = await hauteur( ep, dir );
				check( h0 === PLANCHER, `🔴 ${ langue } — une description COURTE garde la hauteur d’avant : ${ h0 } px (plancher ${ PLANCHER }) — pas collée au plafond` );

				await remplir( ep, dir, paragraphes( 12, 'Paragraphe' ) );
				const h1 = await hauteur( ep, dir );
				check( h1 > h0, `🎯 ${ langue } — l’éditeur GRANDIT avec le contenu : ${ h0 } → ${ h1 } px` );

				await remplir( ep, dir, paragraphes( 200, 'Paragraphe' ) );
				const h2 = await hauteur( ep, dir );
				check( h2 <= PLAFOND, `🎯 ${ langue } — et il reste BORNÉ à ${ PLAFOND } px : ${ h2 } px sur 200 paragraphes` );
				check( h2 >= h1, `${ langue } — le plafond borne, il ne rétrécit pas (${ h1 } → ${ h2 } px)` );
				// 🚨 SANS CE TÉMOIN, LA LIGNE CI-DESSUS EST CREUSE. « ≤ 500 » est vrai
				// d'un éditeur figé à 132 px — c'est-à-dire du défaut qu'on corrige :
				// mesuré, il passait AVANT le correctif. Le plafond n'est éprouvé que
				// s'il est ATTEINT. 200 paragraphes valent ~4 800 px de contenu : s'ils
				// ne le touchent pas, c'est que le redimensionnement ne marche pas.
				check( h2 >= PLAFOND - 30, `🔴 ${ langue } — le plafond est réellement ATTEINT (${ h2 } px pour ${ PLAFOND }) — sinon « ≤ plafond » ne prouverait rien` );

				// AC2 — « Enregistrer » reste atteignable : présent, et le document ne
				// s'étire pas indéfiniment sous l'éditeur.
				const btn = await ep.evaluate( () => {
					const b = document.querySelector( '#aod-cd-product-form button[type="submit"]' );
					if ( ! b ) { return null; }
					const r = b.getBoundingClientRect();
					return { haut: Math.round( r.top + window.scrollY ), page: Math.round( document.documentElement.scrollHeight ) };
				} );
				check( !! btn, `${ langue } — le bouton « Enregistrer » est toujours dans le formulaire` );
				if ( btn ) {
					console.log( `  ⓘ ${ langue } — hauteurs ${ h0 } → ${ h1 } → ${ h2 } px (plafond ${ PLAFOND }) · « Enregistrer » à ${ btn.haut } px sur ${ btn.page } px de page` );
				}

				// ── 47.6 · La limite de poids est ANNONCÉE, et c'est la vraie ──────
				//
				// 🚨 CE QUE CE BLOC PROUVE, ET CE QU'IL NE PROUVE PAS. Il prouve que
				// l'écran ANNONCE la limite avant l'envoi, et qu'il annonce LA MÊME que
				// celle du serveur — c'est-à-dire qu'il n'y a pas deux sources de
				// vérité. Il ne prouve PAS le refus : celui-ci est côté serveur, et
				// c'est `tests/unit/test-upload-borne.php` qui l'éprouve en appelant
				// `handle()`. Le contrôle JS n'est qu'un confort, il ne protège rien —
				// une porte qui le prendrait pour la barrière mentirait sur le produit.
				const limite = await ep.evaluate( () => {
					const notes = [ ...document.querySelectorAll( '.aod-cd-uplimit' ) ];
					return {
						n: notes.length,
						texte: notes.length ? notes[ 0 ].textContent.trim() : '',
						js: ( window.AOD_CD && window.AOD_CD.maxUpload ) || 0,
						msg: ( window.AOD_CD && window.AOD_CD.i18nTooBig ) || '',
					};
				} );
				check( limite.n >= 3, `${ langue } — la limite est annoncée sous les zones de dépôt du formulaire produit (${ limite.n } mentions)` );
				check( limite.js > 0, `${ langue } — la borne est exposée au JS (CD.maxUpload = ${ limite.js } octets)` );
				check( /\d/.test( limite.texte ), `🎯 ${ langue } — l’annonce porte un CHIFFRE, pas une formule vague : « ${ limite.texte } »` );
				check( '' !== limite.msg, `${ langue } — le message de refus côté client est localisé : « ${ limite.msg } »` );
				// 🔴 L'annonce et la borne JS doivent parler du MÊME nombre. Un « 5 Mo »
				// affiché au-dessus d'un refus à 2 Mo serait exactement le mensonge
				// d'écran que la 47.6 corrige.
				const mo = Math.round( limite.js / 1024 / 1024 );
				check(
					limite.texte.includes( String( mo ) ) && limite.msg.includes( String( mo ) ),
					`🔴 ${ langue } — annonce, message et borne JS disent le MÊME chiffre (${ mo } Mo)`
				);
			} finally {
				await eb.close();
			}
		}
	}

	/* ══ 6. UN VRAI 5 Mo PASSE, UN VRAI 6 Mo NE PASSE PAS (47.6bis) ══
	 *
	 * 🚨 LA SEULE PREUVE QUI RÉPOND À L'ESSAI DE L'OWNER. Il a envoyé une photo de
	 * 2,8 Mo depuis /gestion : refusée. Aucun contrôle du dépôt ne pouvait le voir
	 * — `tests/unit/test-upload-borne.php` L'AVOUE dans son propre en-tête :
	 * `_wp_handle_upload()` exige `is_uploaded_file()`, vrai uniquement dans une
	 * requête HTTP `multipart`, donc hors d'atteinte sous WP-CLI. Un navigateur,
	 * lui, en fait une vraie.
	 *
	 * 🚨 LES DEUX SENS, SINON RIEN N'EST PROUVÉ. « Ça passe » seul serait vert avec
	 * la borne applicative supprimée ; « ça refuse » seul serait vert avec le
	 * plafond PHP resté à 2 Mo. C'est la même exigence que le miroir A/B de la
	 * Phase 22 et que le compte B de la porte `confirmateur` (47.5).
	 *
	 * ⚠️ DES IMAGES VRAIES, PAS DES OCTETS. `media_handle_upload()` compare le
	 * contenu à l'extension : un `.jpg` rempli de bruit serait refusé pour la
	 * mauvaise raison, et la porte accuserait la borne. On génère donc de vrais
	 * JPEG (bruit coloré incompressible sur de grandes dimensions), et on LIT leur
	 * taille sur disque avant de les envoyer.
	 *
	 * 🔴 Le produit créé et ses attachements sont supprimés en sortie.
	 */
	{
		console.log( '\n— Envoi réel d’une photo lourde (47.6bis) —' );
		const os = require( 'os' );
		const fsp = require( 'fs' );

		// Générés PAR PHP dans le conteneur (GD y est), puis recopiés côté hôte :
		// Node n'a pas d'encodeur JPEG, et un PNG de 5 Mo demanderait des
		// dimensions absurdes.
		const genere = ( nom, w, h, q ) => {
			wp( [ 'eval', `$im=imagecreatetruecolor(${ w },${ h });`
				+ `for($y=0;$y<${ h };$y+=2)for($x=0;$x<${ w };$x+=2)`
				+ `imagesetpixel($im,$x,$y,imagecolorallocate($im,($x*7+$y*13)%256,($x*3+$y*5)%256,($x*11+$y*17)%256));`
				+ `imagejpeg($im,"/tmp/${ nom }",${ q });imagedestroy($im);echo filesize("/tmp/${ nom }");` ] );
			const dest = path.join( os.tmpdir(), nom );
			execFileSync( 'docker', [ 'compose', 'exec', '-T', 'wpcli', 'cat', `/tmp/${ nom }` ], { cwd: ROOT, maxBuffer: 64 * 1024 * 1024, stdio: [ 'ignore', fsp.openSync( dest, 'w' ), 'ignore' ] } );
			return { chemin: dest, octets: fsp.statSync( dest ).size };
		};

		// ⚠️ `-d memory_limit` n'est pas passable ici : `wp eval` tourne avec la
		// conf du conteneur (128 Mo). On reste donc sous des dimensions modestes,
		// et on VÉRIFIE la taille obtenue plutôt que de la supposer.
		const petite = genere( 'aod-r6-3mo.jpg', 2600, 1950, 88 ); // vise ~3 Mo
		const grosse = genere( 'aod-r6-7mo.jpg', 3400, 2550, 96 ); // vise > 5 Mo

		console.log( `  ⓘ fixtures générées — ${ ( petite.octets / 1048576 ).toFixed( 2 ) } Mo et ${ ( grosse.octets / 1048576 ).toFixed( 2 ) } Mo (lues sur disque)` );
		check( petite.octets > 2.8 * 1048576 && petite.octets < 5 * 1048576, `Témoin : la 1re fixture dépasse les 2,8 Mo de l’essai de l’owner et reste sous 5 Mo (${ ( petite.octets / 1048576 ).toFixed( 2 ) } Mo)` );
		check( grosse.octets > 5 * 1048576, `Témoin : la 2de fixture dépasse la borne de 5 Mo (${ ( grosse.octets / 1048576 ).toFixed( 2 ) } Mo)` );

		const nomProduit = 'Recette 47.6bis ' + Date.now();
		const { browser: ub, page: up } = await launch( { state: false } );
		try {
			await login( up );

			// ⚠️ On CAPTURE les réponses au lieu de les prédire. Une première
			// rédaction filtrait sur `request().postData()` : il est NUL sur un POST
			// `multipart/form-data` (Playwright ne décode pas un corps binaire), donc
			// le prédicat n'était jamais vrai et la porte expirait — sur un produit
			// parfaitement sain. Le corps de la RÉPONSE, lui, est toujours lisible.
			const reponses = [];
			up.on( 'response', async ( r ) => {
				if ( ! r.url().includes( 'admin-ajax.php' ) ) { return; }
				const j = await r.json().catch( () => null );
				if ( j && ( true === j.success || false === j.success ) ) { reponses.push( j ); }
			} );

			/** Remplit le formulaire « nouveau produit » et envoie le fichier donné.
			 *  `galerie` (facultatif) part dans la MÊME soumission : c'est ce qui
			 *  permet de vérifier qu'un refus sur la photo principale n'emporte plus
			 *  le reste de la saisie. */
			const envoyer = async ( fichier, galerie ) => {
				await up.goto( `${ BASE }/gestion/products?action=new`, { waitUntil: 'networkidle' } );
				await up.fill( 'input[name="name[fr]"]', nomProduit );
				await up.fill( 'input[name="regular_price"]', '1500' );
				await up.setInputFiles( '.aod-cd-imgfile', fichier );
				if ( galerie ) { await up.setInputFiles( '.aod-cd-galleryfile', galerie ); }
				const avant = reponses.length;
				await up.click( '#aod-cd-product-form button[type="submit"]' );
				for ( let i = 0; i < 120 && reponses.length === avant; i++ ) {
					await up.waitForTimeout( 500 );
				}
				// ⚠️ Sur un succès, le JS REDIRIGE vers la liste. Sans cette pause, le
				// `goto` de l'envoi suivant est interrompu par cette navigation — et
				// la porte échoue sur un produit parfaitement sain.
				await up.waitForTimeout( 2500 );
				return reponses.length > avant ? reponses[ reponses.length - 1 ] : null;
			};

			// ── A/B · une photo de 3 Mo, refusée AVANT la 47.6bis, doit passer ──
			const res1 = await envoyer( petite.chemin );
			check( !! ( res1 && res1.success ), `🎯 Une photo de ${ ( petite.octets / 1048576 ).toFixed( 2 ) } Mo est ACCEPTÉE (elle était refusée avant la 47.6bis)`, JSON.stringify( res1 && res1.data ) );

			// L'attachement existe VRAIMENT — vérifié en base, pas à l'écran.
			const att = wp( [ 'eval', `$p = get_page_by_title( ${ JSON.stringify( nomProduit ) }, OBJECT, 'product' );`
				+ `echo $p ? (int) get_post_thumbnail_id( $p->ID ) : 0;` ] );
			check( parseInt( att, 10 ) > 0, `🔴 L’attachement est bien créé en base (id ${ att }) — pas seulement un message vert à l’écran` );

			// ── C · au-dessus de 5 Mo : refus par NOTRE borne, pas par PHP ──
			//
			// 🚨 LE REFUS N'INTERROMPT PLUS LA SOUMISSION — et c'est un changement de
			// contrat, trouvé en revue de la Phase 47. Avant, un refus sur la photo
			// PRINCIPALE sortait en `wp_send_json_error()`, donc AVANT la galerie et
			// avant l'écriture de `_aod_options` : tout le reste de la saisie du
			// gérant était jeté en silence, alors que titre, prix et stock venaient
			// d'être enregistrés. Un produit à moitié écrit, sans un mot.
			// Le refus est désormais COMPTÉ comme ceux de la galerie (patron 47.6) et
			// remonte en AVERTISSEMENT sur une réponse en succès.
			//
			// ⚠️ On envoie donc une galerie VALIDE dans la même soumission : c'est
			// elle qui prouve le correctif. Vérifier seulement « ça répond en succès »
			// aurait été vert avec un code qui jette toujours la galerie.
			const res2 = await envoyer( grosse.chemin, petite.chemin );
			const msg2 = ( res2 && res2.data && ( res2.data.warning || res2.data.message ) ) || '';
			check( !! ( res2 && true === res2.success ), `🎯 Une photo de ${ ( grosse.octets / 1048576 ).toFixed( 2 ) } Mo n’interrompt plus la soumission`, JSON.stringify( res2 && res2.data ) );
			check( /5\s*Mo/.test( msg2 ), `🔴 …mais elle est REFUSÉE, par NOTRE borne, avec le chiffre : « ${ msg2 } »` );

			// Le fond de l'affaire, mesuré en base : la photo principale n'est PAS
			// posée (le refus est réel), et la galerie de la même soumission, SI.
			const etat2 = wp( [ 'eval', `$ps = get_posts( array( 'post_type' => 'product', 'title' => ${ JSON.stringify( nomProduit ) }, 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'DESC' ) );`
				+ `$p = $ps ? $ps[0] : null;`
				+ `$g = $p ? array_filter( explode( ',', (string) get_post_meta( $p->ID, '_product_image_gallery', true ) ) ) : array();`
				+ `echo $p ? ( (int) get_post_thumbnail_id( $p->ID ) ) . '|' . count( $g ) : 'aucun';` ] );
			const [ vignette2, galerie2 ] = String( etat2 ).trim().split( '|' );
			check( '0' === vignette2, `🔴 La photo principale trop lourde n’est PAS attachée (vignette : ${ vignette2 })` );
			check( parseInt( galerie2, 10 ) >= 1, `🔴 …et la GALERIE de la même soumission a bien été enregistrée (${ galerie2 } photo) — c’est tout l’objet du correctif`, `galerie vide : le refus de la photo principale emporte encore le reste de la saisie` );

			// ── D · l'écran annonce bien 5 Mo, plus 2 ──
			const annonce = await up.evaluate( () => {
				const n = document.querySelector( '.aod-cd-uplimit' );
				return { texte: n ? n.textContent.trim() : '', js: ( window.AOD_CD && window.AOD_CD.maxUpload ) || 0 };
			} );
			check( annonce.js === 5 * 1048576, `🎯 La limite annoncée au JS vaut 5 Mo (${ annonce.js } octets)` );
			check( annonce.texte.includes( '5' ), `🎯 L’écran annonce 5 Mo : « ${ annonce.texte } »` );
		} finally {
			await ub.close();
			// 🔴 Purge : le produit, sa vignette, et les fichiers témoins.
			// 🚨 TOUS les produits de ce nom, pas un seul : `save_product()` enregistre
			// le produit AVANT de traiter l'image, donc la seconde tentative en crée
			// un second — celui dont la photo principale est refusée mais qui garde,
			// depuis le correctif de la revue 47, sa galerie. Purger le premier
			// trouvé laisserait l'autre derrière.
			// ⚠️ La GALERIE est purgée elle aussi : la vignette n'est plus le seul
			// attachement que cette porte crée, et un attachement orphelin s'accumule
			// à chaque passage sans que rien ne le dise.
			wp( [ 'eval', `foreach ( get_posts( array( 'post_type' => 'product', 'title' => ${ JSON.stringify( nomProduit ) }, 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {`
				+ `$t = (int) get_post_thumbnail_id( $p->ID ); if ( $t ) { wp_delete_attachment( $t, true ); }`
				+ `foreach ( array_filter( explode( ',', (string) get_post_meta( $p->ID, '_product_image_gallery', true ) ) ) as $g ) { wp_delete_attachment( (int) $g, true ); }`
				+ `wp_delete_post( $p->ID, true ); }`
				+ `echo 'purge ok';` ] );
			for ( const f of [ petite.chemin, grosse.chemin ] ) {
				try { fsp.unlinkSync( f ); } catch ( e ) { /* déjà parti */ }
			}
			wp( [ 'eval', `@unlink('/tmp/aod-r6-3mo.jpg'); @unlink('/tmp/aod-r6-7mo.jpg'); echo 'tmp ok';` ] );
		}
		const reste = wp( [ 'eval', `echo count( get_posts( array( 'post_type' => 'product', 'title' => ${ JSON.stringify( nomProduit ) }, 'post_status' => 'any', 'numberposts' => -1 ) ) );` ] );
		check( reste === '0', `Les produits témoins sont supprimés — le clone ressort identique (restants : ${ reste })` );
	}

	console.log( fails ? `\n❌ ${ fails } échec(s).\n` : '\n✅ La description riche tient FR/AR — Phase 8 prouvée sur ce clone.\n' );
	process.exit( fails ? 1 : 0 );
} )().catch( ( e ) => {
	console.error( '\n💥 ' + e.message + '\n' );
	process.exit( 1 );
} );
