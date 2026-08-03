// Recette — L'ENVOI TRANSPORTEUR EST UN GESTE, PLUS UNE CONSÉQUENCE (Phase 47.2,
// porte écrite en 47.9).
//
// 🚨 CE QU'AUCUNE PORTE NE PROUVAIT. La 47.2 a retiré le hook
// `woocommerce_order_status_aod-confirmed` : confirmer une commande n'expédie
// plus rien. C'est la garantie la plus coûteuse de la phase si elle se perd —
// quelqu'un rebranche le hook, et CHAQUE confirmation recommence à créer un
// colis chez le livreur. Personne ne le verrait avant la facture.
// `tests/unit/test-verrou-envoi.php` garde l'absence de hook EN PHP ; cette
// porte la garde dans le VRAI CYCLE DE VIE, celui où le statut change parce
// qu'un humain a cliqué dans /gestion.
//
// 🔴 AUCUN ENVOI RÉEL — ET C'EST UNE CONTRAINTE, PAS UNE PRÉCAUTION.
// Ce clone porte un compte transporteur VÉRITABLEMENT CONFIGURÉ : un envoi
// d'épreuve créerait un vrai colis, facturé. La porte ouvre donc la fenêtre
// d'envoi et l'ANNULE.
//
// CE QU'ELLE DÉLÈGUE, ET POURQUOI ELLE LE DIT. « Un clic sur le camion crée un
// colis, UNE SEULE FOIS » est prouvé par `tests/unit/test-verrou-envoi.php`
// (R4), qui injecte un transporteur BOUCHON et compte ses appels. Trois façons
// de le refaire ici ont été examinées et écartées en 47.9 :
//   · injecter un bouchon → `ship_order()` résout par `carrier_for()`, sans
//     filtre ; en ajouter un POUR LE TEST serait faire plier le produit — ce que
//     `confirmateur.js` s'interdit déjà explicitement ;
//   · pointer le transporteur vers un hôte injoignable → il faudrait ÉCRIRE les
//     réglages transporteur du gérant (le piège que `confirmateur.js` documente :
//     un POST vide sur `save_shipping` EFFACE la grille) ;
//   · envoyer puis annuler → un colis annulé reste un colis créé.
// Une porte qui prétendrait le prouver mentirait ; celle-ci dit ce qu'elle sait.
//
// ⚠️ COMMANDE JETABLE, créée et purgée par la porte. Confirmer une vraie
// commande du clone changerait son statut et fausserait les chiffres que les
// autres portes observent — « une recette ne doit pas fausser les chiffres
// qu'elle observe » (leçon `description-riche.js`).
//
// Lancé par run.sh : `run envoi-geste.js`. Sortie ≠ 0 = échec.
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );
const { launch, login, ok, BASE, scanPhp } = require( './lib.js' );

const ROOT = path.resolve( __dirname, '..', '..' );
let fails = 0;
const check = ( c, m, extra ) => { if ( ! ok( c, m, extra ) ) { fails++; } };

/** WP-CLI dans le conteneur du clone (jamais `docker exec` global : TEMPLATE.md §3). */
function wp( args ) {
	return execFileSync( 'docker', [ 'compose', 'exec', '-T', 'wpcli', 'wp', ...args ], { cwd: ROOT, encoding: 'utf8' } ).trim();
}

( async () => {
	console.log( `\n▶ L'envoi est un GESTE, pas une conséquence — ${ BASE }\n` );

	// ── Une commande jetable, à un état où l'envoi a un sens ────────────────────
	// `_aod_delivery_type` est EXIGÉ : sans lui `ship_order()` refuse d'emblée
	// (« pas de mode de livraison »), et la porte prouverait l'absence d'envoi
	// pour la mauvaise raison.
	const oid = wp( [ 'eval', `
		$o = new WC_Order();
		$o->set_billing_first_name( 'Recette' ); $o->set_billing_last_name( 'EnvoiGeste' );
		$o->set_billing_phone( '0555000479' );
		$o->update_meta_data( '_aod_delivery_type', 'home' );
		$o->update_meta_data( '_aod_wilaya_code', '16' );
		$o->update_meta_data( '_aod_recette_envoi_geste', '1' );
		$o->set_status( 'pending' );
		$o->save();
		echo $o->get_id();
	` ] );
	check( /^\d+$/.test( oid ), `Commande jetable créée (#${ oid })` );

	const metasDe = () => JSON.parse( wp( [ 'eval', `
		$o = wc_get_order( ${ oid } );
		echo wp_json_encode( array(
			'statut'   => $o ? $o->get_status() : '',
			'tracking' => $o ? (string) $o->get_meta( '_aod_ship_tracking' ) : '',
			'erreur'   => $o ? (string) $o->get_meta( '_aod_ship_error' ) : '',
			'notes'    => $o ? count( wc_get_order_notes( array( 'order_id' => ${ oid } ) ) ) : 0,
		) );
	` ] ) );

	const avant = metasDe();
	check( avant.statut === 'pending' && avant.tracking === '', `Témoin : la commande part sans colis (statut ${ avant.statut }, suivi « ${ avant.tracking } »)` );

	const { browser, page } = await launch( { state: false } );
	try {
		await login( page );

		// 🚨 CE QUE LE TRAÇAGE NAVIGATEUR PEUT ET NE PEUT PAS VOIR — et la première
		// rédaction de cette porte s'est trompée dessus. L'appel au transporteur
		// part du SERVEUR (PHP, `wp_remote_post`), jamais du navigateur : écouter
		// `page.on('request')` ne le verra JAMAIS. Une assertion « 0 requête vers un
		// domaine de livreur » serait donc vraie même pendant un envoi réel — une
		// affirmation invérifiable déguisée en preuve.
		// La trace SERVEUR, elle, existe et est concluante : `_aod_ship_tracking`
		// (colis créé) et `_aod_ship_error` (tentative échouée). C'est sur elles que
		// porte le contrôle. Le traçage navigateur ne garde plus que ce qu'il sait
		// voir : les requêtes AJAX de /gestion.
		const ajaxEnvoi = [];
		page.on( 'request', ( r ) => {
			const d = r.postData();
			if ( d && d.includes( 'aod_cd_ship_send' ) ) { ajaxEnvoi.push( r.url() ); }
		} );

		await page.goto( `${ BASE }/gestion/orders?s=EnvoiGeste`, { waitUntil: 'networkidle' } );
		const html = await page.content();
		check( scanPhp( html ).length === 0, 'Aucune erreur PHP sur la liste Commandes' );

		const ligne = page.locator( 'tr' ).filter( { hasText: 'EnvoiGeste' } );
		check( await ligne.count() === 1, 'La commande jetable est bien listée' );

		/* ══ 1. CONFIRMER N'EXPÉDIE PLUS — le contrôle central ══ */
		console.log( '\n— Confirmer une commande —' );

		await ligne.locator( '.aod-cd-statussel-btn' ).click();
		await page.waitForTimeout( 300 );
		await page.locator( '.aod-cd-statussel-menu:not([hidden]) .aod-cd-statussel-opt[data-status="aod-confirmed"]' ).first().click();
		await page.waitForTimeout( 2500 ); // AJAX + re-rendu de la cellule Suivi.

		const apres = metasDe();
		check( apres.statut === 'aod-confirmed', `La commande est passée à « Confirmée » (statut ${ apres.statut }) — sinon tout ce qui suit serait vrai par vacuité` );
		check( apres.tracking === '', `🎯 AUCUN COLIS créé par la confirmation (suivi « ${ apres.tracking } ») — c'est TOUT le sujet de la 47.2` );
		check( apres.erreur === '', `🔴 …et aucune ERREUR d'envoi non plus (« ${ apres.erreur } ») : rien n'a même été TENTÉ` );
		check( ajaxEnvoi.length === 0, `🔴 Et le handler d'envoi n'a même pas été APPELÉ (${ ajaxEnvoi.length } requête(s) aod_cd_ship_send) — la confirmation ne le déclenche plus` );

		/* ══ 2. …mais le chemin existe : il est devenu un GESTE ══ */
		console.log( '\n— Le camion est un bouton nommé —' );

		await page.reload( { waitUntil: 'networkidle' } );
		const ligne2 = page.locator( 'tr' ).filter( { hasText: 'EnvoiGeste' } );
		const boutonEnvoi = ligne2.locator( '.aod-cd-ship-send' );
		check( await boutonEnvoi.count() === 1, '🎯 Le bouton d’envoi est là : le chemin n’a pas disparu, il est devenu un geste' );
		const libelle = ( await boutonEnvoi.innerText().catch( () => '' ) ).trim();
		check( libelle.length > 0, `Le bouton est NOMMÉ (« ${ libelle } ») — leçon 45.3 : jamais une icône muette` );

		/* ══ 3. La fenêtre s'ouvre, nomme la commande… et on ANNULE ══ */
		console.log( '\n— La fenêtre d’envoi, ouverte puis ANNULÉE (aucun envoi réel) —' );

		// 🔴 ON NE CLIQUE JAMAIS LE BOUTON UNITAIRE. Mesuré en écrivant cette porte :
		// il envoie DIRECTEMENT, sans fenêtre — la fenêtre de récapitulatif
		// appartient au LOT (47.2, décision D4 : « N commandes cochées → Envoyer →
		// fenêtre listant les commandes »). Une première rédaction l'a cliqué et a
		// déclenché une VRAIE tentative d'envoi ; le clone a été vérifié intact
		// (0 numéro de suivi), mais c'était un coup de chance, pas une garantie.
		// On passe donc par le LOT, qui ouvre la fenêtre — et on l'ANNULE.
		await page.evaluate( () => {
			const cb = document.querySelector( '.aod-cd-ordercheck' );
			if ( cb && ! cb.checked ) { cb.click(); }
		} );
		await page.waitForTimeout( 400 );
		await page.locator( '.aod-cd-order-bulk-ship' ).click();
		await page.waitForTimeout( 900 );

		const recap = await page.evaluate( () => {
			const ov = [ ...document.querySelectorAll( '.aod-cd-modal:not(#aod-cd-modal)' ) ].pop();
			if ( ! ov ) { return null; }
			return { texte: ov.textContent.replace( /\s+/g, ' ' ).trim() };
		} );
		check( !! recap, 'La fenêtre de récapitulatif s’ouvre' );
		check( !! recap && /EnvoiGeste|\d{3,}/.test( recap.texte ), 'Elle NOMME la commande visée (récapitulatif de la 47.2)' );

		// 🔴 ANNULER. Jamais « Envoyer » : compte transporteur réel.
		const annuler = page.locator( '.aod-cd-modal:not(#aod-cd-modal) [data-cancel]:not(.aod-cd-modal-backdrop):not(.aod-cd-modal-x)' );
		if ( await annuler.count() ) {
			await annuler.last().click();
		} else {
			await page.keyboard.press( 'Escape' );
		}
		await page.waitForTimeout( 900 );

		check( await page.locator( '.aod-cd-modal:not(#aod-cd-modal)' ).count() === 0, 'La fenêtre se referme à l’annulation' );
		check( ajaxEnvoi.length === 0, `🔴 ANNULER N’A DÉCLENCHÉ AUCUN ENVOI (${ ajaxEnvoi.length } requête(s) aod_cd_ship_send)` );
		

		const fin = metasDe();
		check( fin.tracking === '', `Après tout le parcours, la commande n’a toujours aucun colis (suivi « ${ fin.tracking } »)` );
	} catch ( e ) {
		fails++;
		console.log( '  \x1b[31m✗\x1b[0m Exception : ' + ( e && e.message ? e.message : e ) );
	} finally {
		await browser.close();
		// 🔴 Purge : la commande jetable, repérée par SA MÉTA (jamais par son nom —
		// un homonyme réel ne doit pas pouvoir être emporté).
		wp( [ 'eval', `
			foreach ( wc_get_orders( array( 'limit' => -1, 'status' => 'any' ) ) as $o ) {
				if ( '1' === (string) $o->get_meta( '_aod_recette_envoi_geste' ) ) { $o->delete( true ); }
			}
			echo 'purge ok';
		` ] );
	}

	const reste = wp( [ 'eval', `
		$n = 0;
		foreach ( wc_get_orders( array( 'limit' => -1, 'status' => 'any' ) ) as $o ) {
			if ( '1' === (string) $o->get_meta( '_aod_recette_envoi_geste' ) ) { $n++; }
		}
		echo $n;
	` ] );
	check( reste === '0', `La commande jetable est supprimée (restantes : ${ reste })` );

	// Témoin (DoD §C.2) : publier CE QUI A ÉTÉ INSPECTÉ, pas seulement le verdict.
	console.log(
		'\n  ⓘ périmètre inspecté — 1 commande jetable confirmée DANS /gestion (vrai cycle de vie)'
		+ ' · trace SERVEUR relue : ni _aod_ship_tracking ni _aod_ship_error'
		+ ' · handler aod_cd_ship_send jamais appelé · fenêtre de LOT ouverte puis ANNULÉE'
		+ ' · l’unicité de l’appel transporteur reste prouvée par tests/unit/test-verrou-envoi.php (bouchon injecté)'
	);
	console.log( fails ? `\n❌ ${ fails } échec(s).\n` : '\n✅ Confirmer n’expédie plus — l’envoi est un geste, et il n’est jamais parti tout seul.\n' );
	process.exit( fails ? 1 : 0 );
} )().catch( ( e ) => {
	console.error( '\n💥 ' + e.message + '\n' );
	process.exit( 1 );
} );
