// Recette — LA COMMANDE COD, DE BOUT EN BOUT.
//
// C'était le trou de la recette : on vérifiait que le formulaire est *présent* et
// que le panier Woo est *absent*, mais jamais qu'une commande part vraiment. Or
// c'est le seul geste qui fait vivre la boutique. Ici, on la passe pour de vrai —
// en visiteur anonyme, dans un vrai navigateur — puis on va la relire EN BASE.
//
// On éprouve aussi les couches anti-bot, dans leur ordre réel :
//   honeypot → time-trap → (quota IP) → verrou « 1 commande / 24 h par téléphone ».
//
// À savoir : chaque tentative qui franchit le honeypot et le time-trap CONSOMME du
// quota IP (10/h). La recette purge donc ses propres verrous avant de commencer —
// sinon elle ne serait jouable que trois fois par heure. Cette purge n'est faite
// qu'en local (voir `purge()`).
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );
const { launch, ok, BASE, scanPhp, PURGE_VERROUS_COD } = require( './lib' );

const ROOT = path.resolve( __dirname, '..', '..' );
let fails = 0;
const check = ( c, m, extra ) => { if ( ! ok( c, m, extra ) ) { fails++; } };

/** WP-CLI dans le conteneur du clone (jamais `docker exec` global : cf. TEMPLATE.md §3). */
function wp( args ) {
	return execFileSync(
		'docker',
		[ 'compose', 'exec', '-T', 'wpcli', 'wp', ...args ],
		{ cwd: ROOT, encoding: 'utf8' }
	).trim();
}

/** Un numéro DZ valide et unique à chaque exécution (le verrou 24 h est par numéro). */
function phoneNumber() {
	const n = String( Date.now() ).slice( -8 );
	return `05${ n }`;
}

const CLIENT = 'Recette Automatique'; // Le nom sous lequel la recette commande.

/**
 * Remet le terrain à zéro : les verrous anti-flood des exécutions précédentes, et
 * les commandes qu'elles ont laissées (sinon elles s'accumulent et gonflent le CA
 * affiché au gérant — une recette ne doit pas fausser les chiffres qu'elle observe).
 *
 * Uniquement en local : sur un vrai site, on ne touche ni aux protections ni aux
 * commandes. Le garde-fou est le même que celui de `scripts/dev-seed.php`.
 */
function purge() {
	if ( ! /^https?:\/\/(localhost|127\.0\.0\.1)(:|\/|$)/.test( BASE ) ) {
		console.log( '  ⚠️  Cible non locale : ni les verrous anti-flood ni les commandes de recette ne sont purgés.' );
		console.log( '     La recette reste jouable ~3 fois par heure (quota IP), et laisse ses commandes en base.' );
		return;
	}
	// Purge CIBLÉE des deux verrous anti-flood (quota IP + verrou 24 h par
	// téléphone). C'était un `transient delete --all` : il vidait aussi les caches
	// de tarifs/communes des transporteurs et ceux de /gestion. Cf. lib.js.
	wp( [ 'eval', PURGE_VERROUS_COD ] );
	const n = wp( [ 'eval', `$n = 0; foreach ( wc_get_orders( array( 'limit' => -1, 'status' => 'any' ) ) as $o ) { if ( trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ) === '${ CLIENT }' ) { $o->delete( true ); $n++; } } echo $n;` ] );
	if ( parseInt( n, 10 ) > 0 ) {
		console.log( `  ⓘ ${ n } commande(s) de recette précédente(s) supprimée(s).` );
	}
}

( async () => {
	console.log( `\n▶ Commande COD de bout en bout — ${ BASE }\n` );

	purge();

	// 🚨 Produit JETABLE semé par run.sh — plus jamais un produit du clone.
	// Avant : `wc_get_products( limit 1, status publish )`, soit le DERNIER produit
	// publié, sans vérifier ses options ni son stock. La porte passait ou cassait
	// selon ce que le gérant avait saisi en dernier ; et sur un produit à options
	// obligatoires, le refus venait du JS (avant tout $.post), si bien que les
	// couches anti-bot n'étaient jamais atteintes tout en paraissant vertes.
	const productId = process.env.AOD_COD_PID || '';
	if ( ! productId ) {
		check( false, 'Produit jetable semé (AOD_COD_PID)', 'variable absente : run.sh n’a pas semé' );
		console.log( '' );
		process.exit( 1 );
	}
	const OPT_VALUE = process.env.AOD_COD_OPT || '';
	const raw = wp( [ 'eval', `$p = wc_get_product( ${ parseInt( productId, 10 ) } ); if ( ! $p ) { echo 'AUCUN'; } else { echo $p->get_id() . '|' . get_permalink( $p->get_id() ) . '|' . ( $p->is_purchasable() ? 1 : 0 ); }` ] );
	if ( raw === 'AUCUN' ) {
		check( false, 'Le produit jetable est lisible', `#${ productId } introuvable` );
		console.log( '' );
		process.exit( 1 );
	}
	const [ , productUrl, purchasable ] = raw.split( '|' );
	check( purchasable === '1', `Produit jetable #${ productId } achetable`, productUrl );

	// Turnstile actif = pas de jeton en test → la soumission serait refusée à juste
	// titre. On le dit au lieu de rendre un faux échec.
	const turnstile = wp( [ 'eval', `echo ( class_exists( 'AOD_COD_Turnstile' ) && AOD_COD_Turnstile::instance()->is_enabled() ) ? 'on' : 'off';` ] );
	if ( turnstile === 'on' ) {
		console.log( '\n  ⏭️  RETIRÉ (pas un succès) — Turnstile est actif : la soumission réelle ne peut pas' );
		console.log( '     être testée sans jeton Cloudflare. Désactive-le le temps de la recette.' );
		console.log( '     ⚠️  Le tunnel de vente n’a donc PAS été prouvé sur ce clone.\n' );
		process.exit( 3 ); // 3 = retiré. À 0, le bilan de run.sh le comptait comme un succès.
	}

	const { browser, page, console_ } = await launch( { state: false } ); // visiteur anonyme

	/** Remplit le formulaire. Ne soumet pas. */
	async function fill( phone ) {
		await page.fill( '.aod-cod__form input[name="name"]', 'Recette Automatique' );
		await page.fill( '.aod-cod__form input[name="phone"]', phone );
		const wilaya = page.locator( '.aod-cod__form select[name="wilaya"]' );
		await wilaya.selectOption( '16' ); // Alger.
		// La commune se peuple en JS après le choix de la wilaya.
		await page.waitForFunction(
			() => document.querySelector( '.aod-cod__form select[name="commune"]' )?.options.length > 1
		);
		const commune = page.locator( '.aod-cod__form select[name="commune"]' );
		await commune.selectOption( { index: 1 } );
		const address = page.locator( '.aod-cod__form input[name="address"], .aod-cod__form textarea[name="address"]' );
		if ( await address.count() ) { await address.first().fill( 'Rue de la recette, 16000' ); }
		await pickOptions();
	}

	/**
	 * Choisit une valeur pour CHAQUE section d'options de l'offre sélectionnée.
	 *
	 * Sans ça, `collectItems()` rend `missing = true` et le JS coupe AVANT le
	 * `$.post` : plus aucune requête ne part, et les assertions anti-bot ci-dessous
	 * deviennent vraies pour la mauvaise raison. Les sections sont dupliquées par
	 * (offre × unité) : on ne traite que le panneau de l'offre sélectionnée.
	 *
	 * Deux rendus possibles : `select.aod-cod__optselect` (display = dropdown, dont
	 * la première option vaut ""), sinon des radios — dont le contrôle est
	 * visuellement masqué, d'où le clic sur le `label.aod-cod__opt` porteur du `for`.
	 */
	async function pickOptions() {
		const panel = page.locator( '.aod-cod__offer-card.is-selected .aod-cod__offer-panel:not([hidden])' ).first();
		if ( ! ( await panel.count() ) ) { return; }
		const secs = panel.locator( '.aod-cod__optsec' );
		for ( let i = 0, n = await secs.count(); i < n; i++ ) {
			const sec = secs.nth( i );
			const dd = sec.locator( 'select.aod-cod__optselect' );
			if ( await dd.count() ) {
				await dd.selectOption( { index: 1 } ); // index 0 = « — Choisir — »
				continue;
			}
			const lbl = sec.locator( 'label.aod-cod__opt' ).first();
			if ( await lbl.count() ) { await lbl.click(); }
		}
	}

	/** Soumet et rend { done, error } — le panneau de remerciement, ou le message d'erreur. */
	async function submit() {
		await page.click( '.aod-cod__submit' );
		await page.waitForFunction(
			() => document.querySelector( '.aod-cod__done' )
				|| ( document.querySelector( '.aod-cod__msg' )?.textContent || '' ).trim().length > 0,
			null,
			{ timeout: 15000 }
		);
		// Au succès, le formulaire ENTIER est remplacé par le remerciement : le
		// paragraphe de message n'existe alors plus. Le lire sans vérifier ferait
		// échouer le test au moment précis où il devrait réussir.
		const done = await page.locator( '.aod-cod__done' ).count() > 0;
		const msg = page.locator( '.aod-cod__msg' );
		const error = ( await msg.count() ) ? ( ( await msg.textContent() ) || '' ).trim() : '';
		return { done, error };
	}

	// ─────────────────────────────────────────────── 1. Le formulaire est là ───
	await page.goto( productUrl, { waitUntil: 'domcontentloaded' } );
	check( await page.locator( '.aod-cod__form' ).count() > 0, 'La fiche produit porte le formulaire COD' );
	check( await page.locator( 'a[href*="add-to-cart"], button[name="add-to-cart"]' ).count() === 0, 'Aucun bouton « Ajouter au panier » sur la fiche' );
	check( scanPhp( await page.content() ).length === 0, 'Aucune erreur PHP servie sur la fiche produit' );


	/* ── Motiver les refus ──────────────────────────────────────────────────
	   Ces assertions se contentaient de `! res.done` : N'IMPORTE quel refus les
	   satisfaisait, `res.error` n'étant qu'un 3ᵉ argument décoratif. C'est ainsi
	   qu'elles sont restées vertes alors que le refus venait d'une option produit
	   non sélectionnée — un court-circuit JS, AVANT le moindre appel au serveur :
	   les couches anti-bot n'avaient donc pas été atteintes du tout.

	   ⚠️ Deux pièges à connaître :
	    · honeypot et time-trap renvoient VOLONTAIREMENT le même message neutre —
	      on ne peut pas les distinguer par le texte, seulement prouver qu'on a bien
	      atteint le serveur ;
	    · le serveur CUMULE ses erreurs dans une seule chaîne : chercher un motif ne
	      suffit pas, il faut aussi exiger l'absence du motif « options ». */
	const MSG_NEUTRE  = /erreur est survenue|حدث خطأ/i;
	const MSG_TEL     = /num[ée]ro|رقم/i;
	const MSG_VERROU  = /d[ée]j[àa] été enregistrée|24\s?h|مسجل/i;
	const MSG_OPTIONS = /choisir|s[ée]lectionner toutes les options|اختر/i;

	/** Refus attendu, POUR LE BON MOTIF. */
	function refus( res, motif, libelle ) {
		const ok = ! res.done && motif.test( res.error ) && ! MSG_OPTIONS.test( res.error );
		check( ok, libelle, res.error || '(aucun message)' );
	}

	// ──────────────────────────────────────────────────────── 2. Honeypot ───
	// Le champ piège est invisible : un humain ne le remplit jamais. On le remplit
	// comme le ferait un bot qui arrose tous les champs.
	await fill( phoneNumber() );
	await page.evaluate( () => { document.querySelector( '#aod-cod-url' ).value = 'http://bot.tld'; } );
	await page.waitForTimeout( 3500 ); // On laisse passer le time-trap : c'est le honeypot qu'on éprouve.
	let res = await submit();
	refus( res, MSG_NEUTRE, 'Honeypot : refus SERVEUR au message neutre (la requête est bien partie)' );

	// ─────────────────────────────────────────────────────── 3. Time-trap ───
	// Rechargement, puis soumission en moins de 3 s : trop rapide pour un humain.
	await page.goto( productUrl, { waitUntil: 'domcontentloaded' } );
	await fill( phoneNumber() );
	res = await submit(); // Immédiat : on ne temporise pas.
	refus( res, MSG_NEUTRE, 'Time-trap : refus SERVEUR au message neutre (< 3 s)' );

	// ──────────────────────────────────────────── 4. Téléphone invalide ───
	await page.goto( productUrl, { waitUntil: 'domcontentloaded' } );
	await fill( '0123456789' ); // Ne commence pas par 05/06/07.
	await page.waitForTimeout( 3500 );
	res = await submit();
	refus( res, MSG_TEL, 'Un numéro hors format DZ est refusé, et le refus NOMME le téléphone' );

	// ───────────────────────────────── 5. LA commande : elle doit passer ───
	const phone = phoneNumber();
	await page.goto( productUrl, { waitUntil: 'domcontentloaded' } );
	await fill( phone );
	await page.waitForTimeout( 3500 );
	res = await submit();
	check( res.done, 'Une commande COD légitime est acceptée', res.error );

	const done = await page.locator( '.aod-cod__done' ).textContent().catch( () => '' );
	const m = ( done || '' ).match( /(\d{2,})/ );
	const orderId = m ? m[ 1 ] : '';
	check( !! orderId, 'Le remerciement affiche le numéro de commande', done.replace( /\s+/g, ' ' ).trim() );

	check( await page.locator( '.aod-cod__form' ).count() === 0, 'Le formulaire est remplacé par le remerciement (pas de double envoi possible)' );
	check( ! /\/(cart|checkout|commande|panier)/.test( page.url() ), 'Aucune redirection vers un tunnel WooCommerce', page.url() );

	// ────────────────────────── 6. La commande existe VRAIMENT, en base ───
	// C'est le cœur du test : le navigateur a dit « merci », mais seul HPOS fait foi.
	if ( orderId ) {
		const dump = wp( [ 'eval', `$o = wc_get_order( ${ orderId } ); if ( ! $o ) { echo 'ABSENTE'; } else { echo $o->get_status() . '|' . $o->get_billing_phone() . '|' . $o->get_total() . '|' . $o->get_payment_method() . '|' . $o->get_meta( '_aod_source' ) . '|' . count( $o->get_items() ); }` ] );
		if ( dump === 'ABSENTE' ) {
			check( false, `La commande #${ orderId } est introuvable en base` );
		} else {
			const [ status, billPhone, total, gateway, source, nbItems ] = dump.split( '|' );
			check( true, `Commande #${ orderId } retrouvée en base (HPOS)` );
			check( status === 'processing', 'Elle est en « en cours » — prête pour le gérant', `statut : ${ status }` );
			check( billPhone.replace( /\D+/g, '' ) === phone, 'Le téléphone enregistré est bien celui saisi', `${ billPhone } vs ${ phone }` );
			check( parseFloat( total ) > 0, 'Le total est calculé côté serveur (jamais 0)', `total : ${ total }` );
			check( gateway === 'cod', 'Le moyen de paiement est le paiement à la livraison', `gateway : ${ gateway }` );
			check( source === 'aod-cod-form', 'Elle est tracée comme venant du formulaire COD' );
			check( parseInt( nbItems, 10 ) >= 1, 'Elle contient au moins un article' );
			if ( OPT_VALUE ) {
				// L'option choisie au navigateur doit être gravée sur la ligne de commande :
				// c'est la preuve que la sélection a traversé le JS, le POST et match_option().
				const metas = wp( [ 'eval', `$o = wc_get_order( ${ orderId } ); $out = ''; foreach ( $o->get_items() as $it ) { foreach ( $it->get_meta_data() as $md ) { $d = $md->get_data(); $out .= $d['key'] . '=' . ( is_scalar( $d['value'] ) ? $d['value'] : '' ) . ';'; } } echo $out;` ] );
				check( metas.includes( OPT_VALUE ), 'L’option choisie est enregistrée sur la ligne de commande', metas || '(aucune méta)' );
			}
		}
	}

	// ──────────────────── 7. Verrou 24 h : le même numéro ne repasse pas ───
	await page.goto( productUrl, { waitUntil: 'domcontentloaded' } );
	await fill( phone ); // Le MÊME numéro que la commande qui vient de passer.
	await page.waitForTimeout( 3500 );
	res = await submit();
	refus( res, MSG_VERROU, 'Verrou 24 h : refus explicite « déjà enregistrée » (≠ quota IP)' );

	// ─────────────────────────────────────────────────────────── Console ───
	// Les « 400 Bad Request » sont NOS rejets anti-bot : le test les a provoqués
	// exprès (honeypot, time-trap, téléphone invalide). Les compter comme des
	// erreurs ferait échouer la recette précisément parce que la protection marche.
	const expected400 = /Failed to load resource.*(400|429)/i;
	const noise = console_.filter( ( l ) => ! /favicon|preload|Turnstile/i.test( l ) && ! expected400.test( l ) );

	// Meta n'accepte pas toutes les devises. Si la boutique vend en DZD (le cas de
	// TOUS les clones de ce template), le pixel refuse le paramètre `currency` de
	// l'événement Purchase : la conversion est bien envoyée, mais sa VALEUR n'est
	// pas mesurée — et rien ne le signale au gérant qui pilote ses pubs dessus.
	// On l'affiche sans faire échouer la recette : le choix de la devise à déclarer
	// aux régies est une décision commerciale, pas un défaut du tunnel.
	const currencyWarn = noise.filter( ( l ) => /currency.*invalid|invalid.*currency/i.test( l ) );
	if ( currencyWarn.length ) {
		console.log( `  ⚠️  Le pixel Meta refuse la devise de la boutique : ${ currencyWarn[ 0 ] }` );
		console.log( '     → la conversion Purchase part, mais sa valeur n\'est PAS comptée par Meta.' );
		console.log( '     → à trancher avec le client (devise de reporting), cf. section Pixels de /gestion.' );
	}

	const real = noise.filter( ( l ) => ! /currency.*invalid|invalid.*currency/i.test( l ) );
	check( real.length === 0, 'Aucune erreur JavaScript pendant le parcours', real.slice( 0, 3 ).join( ' | ' ) );

	await browser.close();

	// ── La commande créée ne doit pas survivre à la recette ──────────────────
	// Elle ne l'était qu'au DÉBUT du run suivant : entre-temps, elle gonflait le CA
	// affiché au gérant — et restait là pour toujours si la recette n'était plus
	// rejouée. « Une recette ne doit pas fausser les chiffres qu'elle observe. »
	if ( orderId && /^https?:\/\/(localhost|127\.0\.0\.1)(:|\/|$)/.test( BASE ) ) {
		const gone = wp( [ 'eval', `$o = wc_get_order( ${ parseInt( orderId, 10 ) } ); if ( $o ) { $o->delete( true ); echo 'ok'; } else { echo 'absente'; }` ] );
		console.log( `  ⓘ Commande de recette #${ orderId } supprimée (${ gone }).` );
	}

	console.log( fails ? `\n❌ ${ fails } échec(s).\n` : '\n✅ Le tunnel COD tient de bout en bout.\n' );
	process.exit( fails ? 1 : 0 );
} )().catch( ( e ) => {
	console.error( '\n💥 ' + e.message + '\n' );
	process.exit( 1 );
} );
