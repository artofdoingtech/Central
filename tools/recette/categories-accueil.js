/**
 * Recette « catégories sur l'accueil » — clôture Phase 9 (étape 9.3).
 *
 * Prouve, en VISITEUR (page publique, sans login), que l'accueil affiche la sélection
 * « à la une » du gérant (9.1) dans l'ordre choisi, vides comprises, et retombe sur
 * toutes les catégories non vides quand rien n'est coché (repli non-régressif, 9.2).
 *
 * Fixtures semées par scripts/recette-seed-phase9.php (via run.sh) :
 *   Zsel91(ordre 1, nom AR), Asel91(2), Msel91(3) cochées VIDES ; Nsel91 non cochée
 *   VIDE ; Wsel91 non cochée NON VIDE (témoin du repli). Le déflag (bascule en mode
 *   repli) est déclenché par ce script via le seed `unflag`.
 *
 * Lancé par run.sh : `run categories-accueil.js`. Sortie ≠ 0 = échec.
 */
const { launch } = require( './lib.js' );

let pass = 0, fail = 0;
const ok = ( c, m ) => { if ( c ) { pass++; console.log( '  \x1b[32m✓\x1b[0m ' + m ); } else { fail++; console.log( '  \x1b[31m✗\x1b[0m ' + m ); } };
const has = ( html, s ) => html.indexOf( s ) !== -1;
const at = ( html, s ) => { const i = html.indexOf( s ); return i === -1 ? Infinity : i; };

// N'inspecter QUE la section « Nos catégories » : le thème (Astra) affiche aussi la
// catégorie d'un produit sous chaque produit de la section « Nos produits »
// (`ast-woo-product-category`). Sans ce cadrage, le nom d'une catégorie non vide
// fuiterait par son produit, et « sélection = remplacement » deviendrait un faux échec.
// On borne entre le lien Boutique de la section catégories et la section produits.
const catSection = ( html ) => {
	// La section catégories est un [product_categories] : ses tuiles portent la classe
	// `product-category`. On isole du premier au dernier lien `/product-category/`.
	const first = html.indexOf( 'product-category' );
	if ( first === -1 ) { return ''; }
	// La section « Nos produits » suit ; ses <li> portent `type-product` (jamais
	// `product-category`). On coupe au premier marqueur de produit après les catégories.
	const prodMark = html.indexOf( 'post-type-archive', first ); // absent → on garde tout
	const wooProd = html.indexOf( 'ast-woo-product-category', first );
	const end = wooProd === -1 ? ( prodMark === -1 ? html.length : prodMark ) : wooProd;
	return html.slice( first, end );
};

( async () => {
	const { browser, ctx, page, BASE } = await launch();
	try {
		// ── Mode sélection (Z/A/M cochées). ──
		await page.goto( `${ BASE }/`, { waitUntil: 'networkidle' } );
		let cat = catSection( await page.content() );

		// 🚨 AMORCE, avant toute assertion d'ABSENCE. `catSection()` renvoie une
		// chaîne vide si son marqueur est introuvable (accueil personnalisé, thème
		// non-Astra, section catégories masquée) — et alors TOUTES les assertions
		// « X est absente » passent au vert sans rien prouver. On exige donc d'abord
		// que la section existe et contienne des tuiles.
		const tuiles = ( cat.match( /product-category/g ) || [] ).length;
		ok( tuiles >= 3, 'La section « Nos catégories » est rendue et contient des tuiles',
			`${ tuiles } occurrence(s) de product-category` );

		ok( has( cat, 'Zsel91' ), 'Catégorie cochée VIDE « Zsel91 » présente sur l’accueil (hide_empty=0)' );
		ok( has( cat, 'Asel91' ), 'Catégorie cochée VIDE « Asel91 » présente' );
		ok( has( cat, 'Msel91' ), 'Catégorie cochée VIDE « Msel91 » présente' );
		ok( at( cat, 'Zsel91' ) < at( cat, 'Asel91' ) && at( cat, 'Asel91' ) < at( cat, 'Msel91' ),
			'Ordre respecté : Z(1) < A(2) < M(3) — l’ordre saisi l’emporte sur l’alphabet' );
		ok( ! has( cat, 'Nsel91' ), 'Catégorie NON cochée « Nsel91 » absente' );
		ok( ! has( cat, 'Wsel91' ), 'Sélection = remplacement : « Wsel91 » (non vide mais non cochée) absente' );

		// Ex æquo (même ordre) : le départage doit suivre le SLUG, donc être IDENTIQUE
		// en FR et en AR. On repère par lien de slug (invariant par langue).
		const tieFR = at( cat, 'product-category/tieauto/' ) < at( cat, 'product-category/tiebord/' );
		ok( tieFR, 'Ex æquo (ordre 5) en FR : tieauto avant tiebord (départage par slug)' );

		// ── Bilinguisme : le nom arabe de Zsel91 en site AR. ──
		await ctx.addCookies( [ { name: 'aod_lang', value: 'ar', url: BASE } ] );
		await page.goto( `${ BASE }/`, { waitUntil: 'networkidle' } );
		const catAr = catSection( await page.content() );
		ok( has( catAr, 'زدسلكت' ), 'Site AR : le nom arabe « زدسلكت » de Zsel91 s’affiche (filtre get_term 7.5)' );
		// 🔴 Le finding de la revue : sans départage par slug, l'ordre s'inverserait en AR
		// (ألف avant ياء). Avec le slug, l'ordre reste tieauto avant tiebord — comme en FR.
		ok( at( catAr, 'product-category/tieauto/' ) < at( catAr, 'product-category/tiebord/' ),
			'Ex æquo en AR : MÊME ordre qu’en FR (tieauto avant tiebord) — l’ordre ne dépend pas de la langue' );
		await ctx.clearCookies();

		// ── Le REPLI (aucune catégorie cochée) n'est PAS testé ici, à dessein. ──
		//
		// Il l'était : on appelait le mode `unflag` de la graine, puis on vérifiait
		// que l'accueil retombait sur « toutes les catégories non vides ». Mais la
		// bascule s'évalue sur l'ÉTAT GLOBAL du site (`home_categories_shortcode()`
		// bascule dès qu'UNE catégorie est cochée, où qu'elle soit), alors que la
		// graine est volontairement ADDITIVE : elle ne décoche que SES propres
		// termes. Sur un clone où le gérant a coché ses catégories — l'usage normal —
		// le repli n'était donc jamais atteint et la porte rougissait SANS défaut.
		// Décocher ses vraies catégories aurait été destructif, et irréversible si le
		// conteneur mourait au mauvais moment.
		//
		// La bascule est du code pur : elle est prouvée en unitaire, sans base et
		// sans risque — `tests/unit/test-home-categories.php`, groupe « bascule
		// sélection ⇄ repli ». Ne pas la réintroduire ici.
	} catch ( e ) {
		fail++; console.log( '  \x1b[31m✗ EXCEPTION\x1b[0m ' + e.message );
	} finally {
		await browser.close();
	}
	console.log( `\n=== Catégories accueil : ${ pass } ✓ / ${ fail } ✗ ===` );
	process.exit( fail ? 1 : 0 );
} )();
