/**
 * Porte `assignation` — assignation des commandes aux confirmateurs, de bout
 * en bout au navigateur (clôture Phase 42).
 *
 * Ce que les cliquets unitaires (test-assignation-commandes.php,
 * test-assignation-accueil.php) ne peuvent PAS voir : le RENDU, et surtout
 * ce qu'un CONFIRMATEUR voit RÉELLEMENT dans son navigateur — leçon Phase
 * 22/D13 : « toute la recette tournait en gérant, qui satisfait toutes les
 * gardes ». Le scénario confirmateur de cette porte se connecte donc avec un
 * VRAI compte confirmateur (`login( page, { user, pass } )`), jamais le
 * gérant, et vérifie un témoin d'identité (`whoAmI`) avant de conclure quoi
 * que ce soit — sans quoi la porte pourrait verdir en ayant testé le mauvais
 * rôle.
 *
 * Couvre :
 *   (a) GÉRANT — le sélecteur d'assignation dans la modale de détail affiche
 *       le confirmateur assigné (ou « Non assignée »).
 *   (b) GÉRANT — le filtre « Non assignées » (toolbar) et son compteur.
 *   (c) GÉRANT — la barre d'action groupée : `<select>` natif « Assigner
 *       à… », assignation de 2 commandes en un geste.
 *   (d) CONFIRMATEUR — ne voit, dans Commandes, QUE ses propres commandes
 *       assignées — PAS une commande assignée à un AUTRE confirmateur, même
 *       dans le même périmètre produit (preuve du cumul 42.3 au navigateur).
 *   (e) GÉRANT — filtre par confirmateur (Phase 43.2) : `?confirmateur=<id>`
 *       isole les commandes de CE confirmateur (ancrage inverse : celles de
 *       l'autre en sont exclues), et le paramètre est ABSENT du <select> pour
 *       un confirmateur (pouvoir gérant strict, même patron que (d)).
 *
 * Fixtures : scripts/recette-seed-assignation.php (créées/purgées par
 * run.sh, patron recette-seed-variante.php + recette-seed-phase22.php).
 */
'use strict';

const { launch, login, whoAmI, scanPhp, sectionRendue, BASE, confirmerMaison } = require( './lib' );

const LOGIN_A = process.env.AOD_P42_LOGIN || '';
const PASS_A  = process.env.AOD_P42_PASS || '';
const LOGIN_B = process.env.AOD_P42_LOGIN2 || '';
const PASS_B  = process.env.AOD_P42_PASS2 || '';
const OID_ASSIGNED_A = process.env.AOD_P42_OID_ASSIGNED_A || '';
const OID_ASSIGNED_B = process.env.AOD_P42_OID_ASSIGNED_B || '';
const OID_UNASSIGNED = process.env.AOD_P42_OID_UNASSIGNED || '';
const OID_BULK1      = process.env.AOD_P42_OID_BULK1 || '';
const OID_BULK2      = process.env.AOD_P42_OID_BULK2 || '';
const UID_A          = process.env.AOD_P42_UID_A || '';
const UID_B          = process.env.AOD_P42_UID_B || '';

let fails = 0;
function ok( cond, label ) {
	console.log( ( cond ? '  ✅ ' : '  ❌ ' ) + label );
	if ( ! cond ) fails++;
}

async function gotoOrders( page ) {
	await page.goto( `${ BASE }/gestion/orders`, { waitUntil: 'networkidle' } );
	return sectionRendue( page );
}

/** Ouvre la modale de détail de la commande $oid et attend le contenu. */
async function openDetail( page, oid ) {
	await page.click( `.aod-cd-order-detail[data-order="${ oid }"]` );
	await page.waitForSelector( '.aod-cd-modal-body .aod-cd-od-head', { timeout: 8000 } );
}

async function closeModal( page ) {
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 200 );
}

/** Texte affiché par le sélecteur d'assignation ouvert dans la modale. */
async function assignSelText( page ) {
	return page.evaluate( () => {
		const el = document.querySelector( '.aod-cd-modal-body .aod-cd-assignsel-text' );
		return el ? el.textContent.trim() : null;
	} );
}

( async () => {
	if ( ! LOGIN_A || ! PASS_A || ! LOGIN_B || ! PASS_B || ! OID_ASSIGNED_A || ! OID_UNASSIGNED || ! OID_BULK1 || ! OID_BULK2 || ! OID_ASSIGNED_B || ! UID_A || ! UID_B ) {
		console.log( '  ❌ Fixtures absentes (AOD_P42_* manquantes) — seed manquant ?' );
		process.exit( 1 );
	}

	const { browser, ctx, page, console_ } = await launch( { width: 1440, height: 900 } );

	/* --- (a) GÉRANT : le sélecteur affiche le confirmateur assigné / « Non assignée » --- */
	await login( page );
	let rendue = await gotoOrders( page );
	ok( 'orders' === rendue, `Routage : /gestion/orders rend bien « orders » (rendu : ${ rendue })` );
	let orderPhp = scanPhp( await page.content() );
	ok( 0 === orderPhp.length, 'Commandes — aucune erreur PHP' + ( orderPhp.length ? ` (${ orderPhp[ 0 ] })` : '' ) );

	await openDetail( page, OID_ASSIGNED_A );
	const textAssigned = await assignSelText( page );
	ok( !! textAssigned && 'Non assignée' !== textAssigned, `Commande ASSIGNÉE : le sélecteur affiche un nom de confirmateur (lu : « ${ textAssigned } »)` );
	await closeModal( page );

	await openDetail( page, OID_UNASSIGNED );
	const textUnassigned = await assignSelText( page );
	ok( 'Non assignée' === textUnassigned, `Commande NON assignée : le sélecteur affiche « Non assignée » (lu : « ${ textUnassigned } »)` );
	await closeModal( page );

	/* --- (b) GÉRANT : filtre « Non assignées » + compteur ------------------ */
	const countBefore = await page.evaluate( () => {
		const el = document.querySelector( '.aod-cd-unassigned-toggle .aod-cd-seg__count' );
		return el ? parseInt( el.textContent, 10 ) : null;
	} );
	ok( null !== countBefore && countBefore >= 3, `Compteur « Non assignées » présent et ≥ 3 (nos 3 fixtures non assignées) — lu : ${ countBefore }` );

	await page.click( '.aod-cd-unassigned-toggle' );
	await page.waitForLoadState( 'networkidle' );
	const idsFiltered = await page.evaluate( () => Array.from( document.querySelectorAll( '.aod-cd-ordercheck' ) ).map( ( c ) => c.value ) );
	ok( idsFiltered.includes( OID_UNASSIGNED ), 'Filtre actif : la commande NON assignée apparaît dans la liste filtrée' );
	ok( ! idsFiltered.includes( OID_ASSIGNED_A ) && ! idsFiltered.includes( OID_ASSIGNED_B ), '🔴 Filtre actif : les commandes ASSIGNÉES (A et B) n’apparaissent PAS' );

	/* --- (c) GÉRANT : barre d'action groupée — assigner en lot ------------- */
	const hasAssignBar = await page.evaluate( () => !! document.querySelector( '.aod-cd-order-bulk-assign-select' ) );
	ok( hasAssignBar, 'Barre d’action groupée : le <select> « Assigner à… » est rendu (gérant)' );

	await page.check( `.aod-cd-ordercheck[value="${ OID_BULK1 }"]` );
	await page.check( `.aod-cd-ordercheck[value="${ OID_BULK2 }"]` );
	await page.selectOption( '.aod-cd-order-bulk-assign-select', { label: `Recette ${ LOGIN_A }` } );
	await page.click( '.aod-cd-order-bulk-assign' );
	await page.waitForTimeout( 1200 ); // AJAX + retrait des lignes + toast.

	const idsAfterBulk = await page.evaluate( () => Array.from( document.querySelectorAll( '.aod-cd-ordercheck' ) ).map( ( c ) => c.value ) );
	ok( ! idsAfterBulk.includes( OID_BULK1 ) && ! idsAfterBulk.includes( OID_BULK2 ), '🔴 Assignation groupée : les 2 lignes assignées disparaissent de la vue « Non assignées »' );

	/* --- (d) GÉRANT : traçabilité « assigné par » — le fragment « Admin » (43.4)
	 *
	 * 🚨 L'ANCRAGE NÉGATIF EST LA MOITIÉ IMPORTANTE DE CETTE SECTION. Le
	 * fragment n'a de valeur que s'il DISTINGUE : une porte qui vérifie
	 * seulement « Admin apparaît » passerait au vert sur un code qui l'affiche
	 * PARTOUT — c'est-à-dire sur le défaut exact que 43.4 doit empêcher (une
	 * assignation automatique qui se fait passer pour un choix du gérant).
	 * OID_ASSIGNED_A est semé assigné DIRECTEMENT en base, sans signature :
	 * c'est notre témoin « tourniquet ».
	 *
	 * ⚠️ Le rechargement n'est pas une commodité : `applyAssign()` ne met à jour
	 * que le sélecteur de la modale, jamais la cellule « Assigné à » de la ligne
	 * (asymétrie pré-existante avec Abandonnées — hors périmètre de cette story).
	 * Lire la cellule sans recharger testerait un DOM périmé.                */
	await page.goto( `${ BASE }/gestion/orders`, { waitUntil: 'networkidle' } );

	/** Lit la cellule « Assigné à » de la ligne d'une commande. */
	const cellAssigned = ( oid ) => page.evaluate( ( id ) => {
		const cb = document.querySelector( `.aod-cd-ordercheck[value="${ id }"]` );
		const tr = cb ? cb.closest( 'tr' ) : null;
		const td = tr ? tr.querySelector( '.aod-cd-cell-assigned' ) : null;
		if ( ! td ) return null;
		return {
			texte: td.textContent.replace( /\s+/g, ' ' ).trim(),
			admin: !! td.querySelector( '.aod-cd-assigned-by' ),
			nom: !! td.querySelector( '.aod-cd-assigned-name' ),
		};
	}, oid );

	const cellBulk = await cellAssigned( OID_BULK1 );
	ok( !! cellBulk && cellBulk.nom, `ANCRAGE : la cellule « Assigné à » de la commande assignée en lot porte un nom (lu : « ${ cellBulk ? cellBulk.texte : '—' } »)` );
	ok( !! cellBulk && cellBulk.admin, '🔴 Assignée PAR LE GÉRANT (lot, depuis l’interface) : le fragment « Admin » est affiché' );

	const cellSeed = await cellAssigned( OID_ASSIGNED_A );
	ok( !! cellSeed && cellSeed.nom, `ANCRAGE : la commande assignée sans signature porte bien un nom (lu : « ${ cellSeed ? cellSeed.texte : '—' } »)` );
	ok( !! cellSeed && ! cellSeed.admin, '🔴 ANCRAGE NÉGATIF : assignée SANS signature (patron tourniquet) → AUCUN « Admin » — c’est cette différence qui porte l’information' );

	/* Le même geste, mais par le sélecteur individuel de la modale
	 * (`aod_cd_order_assign`) — l'autre chemin d'écriture manuel, distinct de
	 * `bulk_assign()`.
	 *
	 * ⚠️ Cobaye : OID_ASSIGNED_B, qui part assignée SANS signature (semée
	 * directement en base). Ni OID_UNASSIGNED ni les commandes du lot :
	 * les sections (e) et (d-confirmateur) plus bas s'appuient sur leur état
	 * (« A porte 3 commandes », « A ne voit pas une commande non assignée »).
	 * On la rend à B en sortie pour que ces prémisses tiennent — seule sa
	 * SIGNATURE change, ce dont aucune autre assertion ne dépend.        */
	const assignerViaModale = async ( oid, uid ) => {
		await openDetail( page, oid );
		await page.click( '.aod-cd-modal-body .aod-cd-assignsel-btn' );
		await page.waitForSelector( '.aod-cd-modal-body .aod-cd-assignsel-menu:not([hidden])', { timeout: 5000 } );
		await page.click( `.aod-cd-modal-body .aod-cd-assignsel-opt[data-value="${ uid }"]` );
		await page.waitForTimeout( 1200 ); // AJAX + état « is-ok ».
		await page.goto( `${ BASE }/gestion/orders`, { waitUntil: 'networkidle' } );
	};

	const cellAvantModale = await cellAssigned( OID_ASSIGNED_B );
	ok( !! cellAvantModale && ! cellAvantModale.admin, 'ANCRAGE : avant le geste, cette commande semée n’affiche PAS « Admin »' );
	await assignerViaModale( OID_ASSIGNED_B, UID_A );
	const cellModale = await cellAssigned( OID_ASSIGNED_B );
	ok( !! cellModale && cellModale.admin, '🔴 Assignée par le gérant depuis la MODALE (`aod_cd_order_assign`) : « Admin » apparaît après rechargement' );
	await assignerViaModale( OID_ASSIGNED_B, UID_B ); // remise en état pour (e) et (d-confirmateur).

	// Le libellé est LITTÉRAL : le nom du gérant ne doit apparaître nulle part
	// dans la colonne (A.20, Constat 3).
	const fuiteAssignant = await page.evaluate( () => {
		const cells = Array.from( document.querySelectorAll( '.aod-cd-cell-assigned' ) );
		return cells.some( ( td ) => /admin/i.test( td.textContent ) && td.textContent.includes( 'admin@' ) );
	} );
	ok( ! fuiteAssignant, '🔴 Le libellé est littéral : aucune identité d’assignant ne fuit dans la colonne' );

	/* Mobile 390 px — la colonne « Assigné à » gagne un fragment ; c'est la
	 * situation qui a coûté 127 px de débordement en 43.3.
	 *
	 * 🚨 ON MESURE LA CELLULE, PAS LA PAGE — et ce n'est pas un adoucissement.
	 * Mesuré le 2026-07-28 : `/gestion/orders` déborde DÉJÀ de 193 px à 390 px,
	 * à l'identique AVEC et SANS ce fragment. Coupables relevés au
	 * `getBoundingClientRect()` : la barre d'action groupée
	 * (`.aod-cd-order-bulk-del` bord droit 583 px, `.aod-cd-order-bulk-assign`
	 * 437 px) et le `<th>` « Note » (598 px) — c'est-à-dire les Phases 39.6/42.5,
	 * pas celle-ci. Le fragment, lui, s'arrête à 372 px : DANS le viewport.
	 * Asserter la page entière ici rendrait cette porte rouge en permanence sur
	 * un défaut qu'elle ne corrige pas — et une porte qui rougit sans rapport
	 * apprend à ignorer la recette (dette D16). Le défaut préexistant est
	 * signalé à part, il n'est pas enterré ici.                            */
	await page.setViewportSize( { width: 390, height: 844 } );
	await page.waitForTimeout( 300 );
	const mesureCell = await page.evaluate( () => {
		const frag = document.querySelector( '.aod-cd-assigned-by' );
		if ( ! frag ) return null;
		const td = frag.closest( 'td' );
		return {
			fragRight: Math.round( frag.getBoundingClientRect().right ),
			cellRight: Math.round( td ? td.getBoundingClientRect().right : 0 ),
			vw: document.documentElement.clientWidth,
		};
	} );
	/* --- Barre d'action groupée : deux défauts trouvés le 2026-07-28 ---------
	 *
	 * 🚨 POURQUOI CES ASSERTIONS VIVENT ICI ET PAS DANS `run.sh rtl`. Cette
	 * porte-là audite DÉJÀ le débordement à 390 px sur les 12 sections… et elle
	 * était VERTE pendant que Commandes débordait de 193 px. Raison : elle
	 * s'exécute sur le clone TEL QUEL, et un clone de développement a zéro
	 * commande — sans lignes, ni tableau ni barre d'action ne sont rendus. Elle
	 * passait sans avoir regardé la chose qui casse. Les défauts qui ne
	 * surviennent qu'avec des DONNÉES doivent être gardés par une porte qui SÈME
	 * ses données : celle-ci.
	 *
	 * (i) La barre était affichée EN PERMANENCE : le JS la pilote par
	 *     `bar.hidden = …`, mais son `display:flex` battait la règle
	 *     `[hidden]{display:none}` de la feuille par défaut du navigateur. Un
	 *     bandeau noir « 0 sélectionnée(s) » avec un bouton « Supprimer (0) »,
	 *     à toutes les largeurs. La leçon était pourtant écrite dans le même
	 *     fichier CSS, mille lignes plus haut, pour quatre menus.
	 * (ii) Une fois affichée, elle ne se repliait pas : ses items sont
	 *     insécables et débordaient de 193 px en 390 px — « Supprimer » hors de
	 *     l'écran, donc inatteignable.
	 *
	 * 🚨 (i) A CHANGÉ D'INVARIANT EN 45.2, et l'assertion a dû être RETOURNÉE,
	 * pas supprimée. La barre est désormais rendue en permanence — à la demande
	 * de l'owner, et parce que ce qui protégeait d'une action à vide n'était pas
	 * l'invisibilité du bouton mais l'impossibilité d'agir. Laisser l'ancien
	 * `not([hidden])` en place aurait produit le pire des verts : une assertion
	 * vraie par construction, qui continue de rassurer sans rien mesurer. On
	 * garde donc la MÊME question — « peut-on supprimer sans avoir rien
	 * sélectionné ? » — en la posant à l'endroit où la réponse vit maintenant :
	 * l'attribut `disabled` des actions.                                      */
	const barreAuRepos = await page.evaluate( () => {
		const bar = document.getElementById( 'aod-cd-order-bulk' );
		if ( ! bar ) return null;
		const actes = [ ...bar.querySelectorAll( '.aod-cd-bulk-act' ) ];
		const cs = getComputedStyle( bar );
		return {
			display: cs.display,
			fond: cs.backgroundColor,
			hauteur: Math.round( bar.getBoundingClientRect().height ),
			actions: actes.length,
			actives: actes.filter( ( b ) => ! b.disabled ).map( ( b ) => b.textContent.trim().slice( 0, 20 ) ),
		};
	} );
	ok( null !== barreAuRepos && barreAuRepos.hauteur > 0, '45.2 — ANCRAGE : rien n’est sélectionné, et la barre est VISIBLE (l’outil s’annonce avant d’être utilisable)' );
	ok(
		!! barreAuRepos && barreAuRepos.actions >= 1,
		`45.2 — TÉMOIN : ${ barreAuRepos ? barreAuRepos.actions : 0 } action(s) inspectée(s) dans la barre (0 rendrait l’assertion suivante vraie par vacuité)`
	);
	ok(
		!! barreAuRepos && 0 === barreAuRepos.actives.length,
		`🔴 45.2 — AUCUNE action n’est activable à sélection vide (activées : ${ barreAuRepos ? JSON.stringify( barreAuRepos.actives ) : '?' }) — c’est la garantie de 2026, portée par \`disabled\` et non plus par le masquage`
	);
	ok(
		!! barreAuRepos && 'rgb(24, 24, 24)' !== barreAuRepos.fond,
		`45.2 — la barre a quitté le fond \`--cd-ink\` pour une surface de la charte (mesuré : ${ barreAuRepos ? barreAuRepos.fond : '?' })`
	);

	await page.check( `.aod-cd-ordercheck[value="${ OID_UNASSIGNED }"]` );
	await page.waitForTimeout( 250 );
	const barreOuverte = await page.evaluate( () => {
		const bar = document.getElementById( 'aod-cd-order-bulk' );
		const de = document.documentElement;
		const actes = [ ...bar.querySelectorAll( '.aod-cd-bulk-act' ) ];
		return {
			hauteur: Math.round( bar.getBoundingClientRect().height ),
			wrap: getComputedStyle( bar ).flexWrap,
			debord: de.scrollWidth - de.clientWidth,
			vw: de.clientWidth,
			actions: actes.length,
			desactivees: actes.filter( ( b ) => b.disabled ).map( ( b ) => b.textContent.trim().slice( 0, 20 ) ),
		};
	} );
	ok( barreOuverte.hauteur > 0, 'ANCRAGE : une ligne cochée, la barre est là (sinon la mesure suivante serait vraie par vacuité)' );
	ok(
		barreOuverte.actions >= 1 && 0 === barreOuverte.desactivees.length,
		`🔴 45.2 — une ligne cochée ARME les ${ barreOuverte.actions } action(s) (restées désactivées : ${ JSON.stringify( barreOuverte.desactivees ) }) — l’ancrage inverse du contrôle au repos : sans lui, une barre dont TOUT serait toujours désactivé passerait les deux`
	);
	ok(
		barreOuverte.debord <= 0,
		`🔴 Barre AFFICHÉE à ${ barreOuverte.vw } px : la page ne déborde pas (mesuré : ${ barreOuverte.debord } px, repli : ${ barreOuverte.wrap }) — sinon « Supprimer » sort de l’écran`
	);
	await page.uncheck( `.aod-cd-ordercheck[value="${ OID_UNASSIGNED }"]` );
	await page.waitForTimeout( 200 );

	ok( null !== mesureCell, 'ANCRAGE : un fragment « Admin » est bien rendu à 390 px (sinon la mesure ne prouverait rien)' );
	ok(
		!! mesureCell && mesureCell.fragRight <= mesureCell.vw && mesureCell.cellRight <= mesureCell.vw,
		`🔴 Mobile 390 px : le fragment et sa cellule tiennent dans le viewport (fragment : ${ mesureCell ? mesureCell.fragRight : '?' } px, cellule : ${ mesureCell ? mesureCell.cellRight : '?' } px, viewport : ${ mesureCell ? mesureCell.vw : '?' } px)`
	);
	await page.setViewportSize( { width: 1440, height: 900 } );
	await page.waitForTimeout( 200 );

	/* --- (d-bis) 43.5 : l'écrasement groupé, et son refus par défaut --------
	 *
	 * 🚨 L'ANCRAGE NÉGATIF EST ICI LA MOITIÉ QUI COMPTE. Vérifier seulement que
	 * « la case cochée réassigne » laisserait passer un code qui réassigne
	 * TOUJOURS — c'est-à-dire le risque exact que la 42.5 avait écarté et que la
	 * décision D1 n'autorise qu'à la demande. On éprouve donc d'abord le REFUS.
	 *
	 * Cobaye : OID_ASSIGNED_B (assignée à B). On la rend à B en sortie — les
	 * sections (e) et confirmateur s'appuient sur cette appartenance.          */
	await page.setViewportSize( { width: 1440, height: 900 } );

	// 🚨 47.7 — la confirmation d'écrasement est passée à la fenêtre MAISON. Le
	// `page.on( 'dialog', d => d.accept() )` qui vivait ici ne se déclenche plus :
	// il n'y a plus de dialogue natif. Sans clic sur « Confirmer », l'assignation
	// ne partait pas — rouge légitime, pas régression produit.
	// ⚠️ La question n'apparaît QUE s'il y a réellement quelque chose à écraser
	// (43.5) : `confirmerMaison()` est donc appelée seulement dans ce cas.

	const assignerEnLot = async ( oid, uid, ecraser ) => {
		await page.goto( `${ BASE }/gestion/orders`, { waitUntil: 'networkidle' } );
		await page.check( `.aod-cd-ordercheck[value="${ oid }"]` );
		await page.selectOption( '.aod-cd-order-bulk-assign-select', String( uid ) );
		if ( ecraser ) { await page.check( '.aod-cd-order-bulk-overwrite' ); }
		await page.click( '.aod-cd-order-bulk-assign' );
		if ( ecraser ) {
			// 47.7 — la fenêtre ne s'ouvre que si des assignations existent (43.5).
			await confirmerMaison( page ).catch( () => {} );
		}
		await page.waitForTimeout( 2400 ); // AJAX + toast + rechargement (hors vue « Non assignées »).
		await page.goto( `${ BASE }/gestion/orders`, { waitUntil: 'networkidle' } );
	};

	// AC 6 — la case existe, et elle est DÉCOCHÉE au chargement.
	await page.goto( `${ BASE }/gestion/orders`, { waitUntil: 'networkidle' } );
	const owEtat = await page.evaluate( () => {
		const b = document.querySelector( '.aod-cd-order-bulk-overwrite' );
		const l = b ? document.querySelector( 'label[for="' + b.id + '"]' ) : null;
		return b ? { present: true, coche: b.checked, label: l ? l.textContent.trim() : null } : { present: false };
	} );
	ok( owEtat.present, 'La case « écraser » est rendue dans la barre groupée (gérant)' );
	ok( !! owEtat.label, `…avec un <label for> associé (lu : « ${ owEtat.label } »)` );
	ok( false === owEtat.coche, '🔴 …et DÉCOCHÉE au chargement — jamais un défaut caché (D1)' );

	// AC 7 — elle se réarme dès que la sélection se vide.
	await page.check( `.aod-cd-ordercheck[value="${ OID_ASSIGNED_B }"]` );
	await page.check( '.aod-cd-order-bulk-overwrite' );
	ok( await page.isChecked( '.aod-cd-order-bulk-overwrite' ), 'ANCRAGE : la case peut bien être cochée' );
	await page.uncheck( `.aod-cd-ordercheck[value="${ OID_ASSIGNED_B }"]` );
	await page.waitForTimeout( 250 );
	ok(
		! ( await page.isChecked( '.aod-cd-order-bulk-overwrite' ) ),
		'🔴 Sélection vidée → la case se RÉARME (un mode destructif ne survit pas à un geste)'
	);

	// AC 9 + refus par défaut : case DÉCOCHÉE, la commande de B ne bouge pas.
	await assignerEnLot( OID_ASSIGNED_B, UID_A, false );
	const cellRefus = await cellAssigned( OID_ASSIGNED_B );
	ok(
		!! cellRefus,
		'🔴 Hors de la vue « Non assignées », la ligne N’A PAS disparu de la liste (piège 43.5)'
	);
	const idsApresRefus = await page.evaluate( ( id ) => {
		const cb = document.querySelector( `.aod-cd-ordercheck[value="${ id }"]` );
		const tr = cb ? cb.closest( 'tr' ) : null;
		const td = tr ? tr.querySelector( '.aod-cd-cell-assigned' ) : null;
		return td ? td.textContent.replace( /\s+/g, ' ' ).trim() : null;
	}, OID_ASSIGNED_B );
	ok(
		!! idsApresRefus && idsApresRefus.indexOf( `Recette ${ LOGIN_B }` ) === 0,
		`🔴 ANCRAGE NÉGATIF : case décochée → la commande reste à B (lu : « ${ idsApresRefus } ») — l’outil ne reprend RIEN sans qu’on le demande`
	);

	// …puis case COCHÉE : elle change bien de mains.
	await assignerEnLot( OID_ASSIGNED_B, UID_A, true );
	const cellEcrase = await cellAssigned( OID_ASSIGNED_B );
	ok(
		!! cellEcrase && cellEcrase.texte.indexOf( `Recette ${ LOGIN_A }` ) === 0,
		`🔴 Case cochée → la commande est RÉASSIGNÉE à A (lu : « ${ cellEcrase ? cellEcrase.texte : '—' } »)`
	);
	ok(
		!! cellEcrase && cellEcrase.admin,
		'🔴 …et porte « Admin » : une réassignation groupée est un geste manuel du gérant (43.4)'
	);

	// Remise en état pour (e) et le parcours confirmateur.
	await assignerEnLot( OID_ASSIGNED_B, UID_B, true );
	const cellRendue = await cellAssigned( OID_ASSIGNED_B );
	ok(
		!! cellRendue && cellRendue.texte.indexOf( `Recette ${ LOGIN_B }` ) === 0,
		'La commande est rendue à B (état attendu par les sections suivantes)'
	);

	const consoleAfterGerant = console_.slice();
	ok( 0 === consoleAfterGerant.length, 'Console (parcours gérant) sans erreur ni avertissement' + ( consoleAfterGerant.length ? ` (${ consoleAfterGerant[ 0 ] })` : '' ) );

	/* --- (e) GÉRANT : filtre par confirmateur (43.2) ----------------------- *
	 * Après (c), A porte désormais 3 commandes (ASSIGNED_A + BULK1 + BULK2) ;
	 * B en porte 1 (ASSIGNED_B). Filtrer sur A doit isoler ses 3, jamais celle
	 * de B — ancrage inverse explicite, même exigence que (d) côté serveur. */
	await page.goto( `${ BASE }/gestion/orders?statut=all&confirmateur=${ UID_A }`, { waitUntil: 'networkidle' } );
	let idsFilterA = await page.evaluate( () => Array.from( document.querySelectorAll( '[data-order]' ) ).map( ( el ) => el.dataset.order ) );
	ok(
		idsFilterA.includes( OID_ASSIGNED_A ) && idsFilterA.includes( OID_BULK1 ) && idsFilterA.includes( OID_BULK2 ),
		'Filtre confirmateur = A : ses 3 commandes (assignée directe + 2 du lot) apparaissent'
	);
	ok( ! idsFilterA.includes( OID_ASSIGNED_B ), '🔴 ANCRAGE INVERSE : filtre confirmateur = A EXCLUT la commande de B' );

	await page.goto( `${ BASE }/gestion/orders?statut=all&confirmateur=${ UID_B }`, { waitUntil: 'networkidle' } );
	const idsFilterB = await page.evaluate( () => Array.from( document.querySelectorAll( '[data-order]' ) ).map( ( el ) => el.dataset.order ) );
	ok( idsFilterB.includes( OID_ASSIGNED_B ), 'Filtre confirmateur = B : sa commande apparaît' );
	ok( ! idsFilterB.includes( OID_ASSIGNED_A ), '🔴 ANCRAGE INVERSE (symétrique) : filtre confirmateur = B EXCLUT les commandes de A' );

	await page.goto( `${ BASE }/gestion/orders`, { waitUntil: 'networkidle' } );
	const hasConfirmerSelect = await page.evaluate( () => !! document.querySelector( '#aod-cd-f-confirmer' ) );
	ok( hasConfirmerSelect, 'Gérant : le <select> confirmateur est bien rendu dans la barre de filtres' );

	/* --- (d) CONFIRMATEUR : ne voit QUE ses propres commandes assignées ---- *
	 * 🚨 LE PIÈGE D13/Phase 22 : se connecter en gérant ici ne prouverait RIEN
	 * (le gérant n'est filtré par aucun périmètre). On se connecte avec un
	 * VRAI compte confirmateur et on vérifie l'identité AVANT de conclure. */
	console_.length = 0;
	await login( page, { user: LOGIN_A, pass: PASS_A } );
	ok( ( await whoAmI( ctx ) ) === LOGIN_A, `Témoin d’identité : la session est bien « ${ LOGIN_A } » (pas le gérant)` );

	rendue = await gotoOrders( page );
	ok( 'orders' === rendue, `Confirmateur : /gestion/orders rend bien « orders » (rendu : ${ rendue })` );
	const confPhp = scanPhp( await page.content() );
	ok( 0 === confPhp.length, 'Confirmateur — aucune erreur PHP' + ( confPhp.length ? ` (${ confPhp[ 0 ] })` : '' ) );

	const idsForA = await page.evaluate( () => Array.from( document.querySelectorAll( '[data-order]' ) ).map( ( el ) => el.dataset.order ) );
	ok( idsForA.includes( OID_ASSIGNED_A ), 'Confirmateur A : voit SA commande assignée' );
	ok( ! idsForA.includes( OID_ASSIGNED_B ), '🔴 Confirmateur A : ne voit PAS la commande assignée à B (même périmètre produit) — preuve du cumul 42.3 au navigateur' );
	ok( ! idsForA.includes( OID_UNASSIGNED ), 'Confirmateur A : ne voit pas non plus une commande non assignée (D4)' );

	// Le sélecteur d'assignation est un pouvoir GÉRANT : absent pour un confirmateur.
	const hasAssignSelForConfirmer = await page.evaluate( () => !! document.querySelector( '.aod-cd-assignsel' ) );
	ok( ! hasAssignSelForConfirmer, 'Confirmateur : le sélecteur d’assignation n’est rendu NULLE PART (pouvoir gérant strict)' );
	const hasBulkAssignForConfirmer = await page.evaluate( () => !! document.querySelector( '.aod-cd-order-bulk-assign-select' ) );
	ok( ! hasBulkAssignForConfirmer, 'Confirmateur : la barre « Assigner à… » n’est pas rendue' );
	// 43.5 : la case « écraser » est un pouvoir gérant au même titre que le
	// sélecteur — elle vit dans le même `if ( $can_assign )`, mais on le VÉRIFIE
	// plutôt que de le déduire du code.
	const hasOverwriteForConfirmer = await page.evaluate( () => !! document.querySelector( '.aod-cd-order-bulk-overwrite' ) );
	ok( ! hasOverwriteForConfirmer, '🔴 Confirmateur : la case « écraser les assignations » n’est rendue NULLE PART' );
	// Filtre confirmateur (43.2) : même pouvoir gérant strict — absent pour un
	// confirmateur, et un `confirmateur=` posté malgré tout resterait sans effet
	// (couvert côté serveur par test-filtre-confirmateur.php).
	const hasConfirmerFilterForConfirmer = await page.evaluate( () => !! document.querySelector( '#aod-cd-f-confirmer' ) );
	ok( ! hasConfirmerFilterForConfirmer, 'Confirmateur : le <select> filtre confirmateur n’est rendu NULLE PART' );

	ok( 0 === console_.length, 'Console (parcours confirmateur) sans erreur ni avertissement' + ( console_.length ? ` (${ console_[ 0 ] })` : '' ) );

	await browser.close();
	console.log( fails ? `\n  ❌ ASSIGNATION : ${ fails } échec(s)` : '  ✅ ASSIGNATION : sélecteur, filtre, lot, et périmètre confirmateur — tout tient au navigateur.' );
	process.exit( fails ? 1 : 0 );
} )().catch( ( e ) => { console.error( e ); process.exit( 2 ); } );
