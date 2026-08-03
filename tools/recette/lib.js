// Socle commun des recettes navigateur. Rien de spécifique à un clone ici :
// l'URL et le compte admin sont lus dans `.env` (le même que celui qui a servi à
// installer la stack), et le binaire Chromium est cherché dans le cache Playwright.
const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( 'playwright-core' );

const ROOT = path.resolve( __dirname, '..', '..' );

/** Lit `.env` à la racine du clone. Pas de dépendance dotenv : le format est trivial. */
function env() {
	const out = {};
	const file = path.join( ROOT, '.env' );
	if ( ! fs.existsSync( file ) ) { return out; }
	for ( const line of fs.readFileSync( file, 'utf8' ).split( '\n' ) ) {
		const m = line.match( /^\s*([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/ );
		if ( ! m ) { continue; }
		// Retire un commentaire de fin de ligne, puis les guillemets éventuels.
		out[ m[ 1 ] ] = m[ 2 ].replace( /\s+#.*$/, '' ).trim().replace( /^["']|["']$/g, '' );
	}
	return out;
}

const ENV = env();

const BASE = process.env.AOD_BASE
	|| ENV.WP_URL
	|| `http://localhost:${ ENV.WP_PORT || '8090' }`;

const USER = process.env.AOD_USER || ENV.WP_ADMIN_USER || 'admin';
const PASS = process.env.AOD_PASS || ENV.WP_ADMIN_PASSWORD || '';

/** Chromium : celui de Playwright, sans imposer le paquet `playwright` complet. */
function chromiumPath() {
	if ( process.env.AOD_CHROMIUM ) { return process.env.AOD_CHROMIUM; }
	const cache = path.join( process.env.HOME || '', '.cache', 'ms-playwright' );
	if ( ! fs.existsSync( cache ) ) { return null; }
	// `chromium-1228` avant `chromium_headless_shell-1228` : on veut le vrai navigateur.
	const dir = fs.readdirSync( cache ).filter( ( d ) => /^chromium-\d+$/.test( d ) ).sort().pop();
	if ( ! dir ) { return null; }
	const exe = path.join( cache, dir, 'chrome-linux64', 'chrome' );
	return fs.existsSync( exe ) ? exe : null;
}

const SHOTS = path.join( __dirname, 'shots' );

// Signature d'une VRAIE erreur PHP servie : le préfixe SEUL ne suffit pas, il
// attraperait nos propres chaînes i18n (ex. `i18nTeamPassNotice`). On exige le
// chemin du fichier fautif.
const PHP_ERROR_RE =
	/(Notice|Warning|Fatal error|Parse error|Deprecated|Recoverable fatal error):[\s\S]{0,300}? in \/var\/www\//g;
const WP_DOING_IT_WRONG = /(_load_textdomain_just_in_time|was called incorrectly|headers already sent)/g;

function scanPhp( html ) {
	const hits = [];
	for ( const re of [ PHP_ERROR_RE, WP_DOING_IT_WRONG ] ) {
		re.lastIndex = 0;
		for ( const m of html.matchAll( re ) ) { hits.push( m[ 0 ].replace( /\s+/g, ' ' ).slice( 0, 120 ) ); }
	}
	return hits;
}

// `state` est ACCEPTÉ mais n'a plus d'effet (Phase 22.1) — et ce n'est pas un
// oubli. Il pilotait le chargement d'une session persistée depuis `.state.json`.
// Ce fichier n'était ÉCRIT nulle part : le mécanisme était dormant, et le jour où
// quelqu'un l'aurait armé, une porte destinée à un rôle serait repartie sur la
// session d'un AUTRE rôle — verte, en testant le mauvais utilisateur. Retiré.
//
// Le paramètre reste accepté parce que **15 appels dans 7 fichiers** écrivent
// `launch( { state: false } )` pour déclarer leur intention : « contexte neuf ».
// Cette intention est désormais la seule possible — tout contexte est neuf.
//
// 🚨 Ne PAS en déduire que `state: false` veut dire « visiteur anonyme ».
// `catalogue-bilingue.js:296` et `description-riche.js:303` l'utilisent PUIS
// appellent `login()`. Un garde-fou « pas de login sur un contexte anonyme »
// casserait ces deux portes.
//
// Nettoyer les 15 sites d'appel touche 7 fichiers, dont 3 des 10 portes qui
// appellent `login()` : à faire d'un seul geste, pas au détour d'une étape qui
// s'interdit de les modifier.
// eslint-disable-next-line no-unused-vars -- `state` : voir le bloc ci-dessus.
async function launch( { width = 1440, height = 900, state = true } = {} ) {
	const exe = chromiumPath();
	if ( ! exe ) {
		console.error( '❌ Chromium introuvable. Installe-le : npx playwright install chromium' );
		console.error( '   (ou pointe AOD_CHROMIUM vers un binaire Chrome/Chromium existant)' );
		process.exit( 2 );
	}
	const browser = await chromium.launch( { executablePath: exe } );
	const ctx = await browser.newContext( { viewport: { width, height } } );
	const page = await ctx.newPage();
	const console_ = [];
	page.on( 'console', ( m ) => {
		if ( m.type() === 'error' || m.type() === 'warning' ) { console_.push( `[${ m.type() }] ${ m.text() }` ); }
	} );
	page.on( 'pageerror', ( e ) => console_.push( `[pageerror] ${ e.message }` ) );
	return { browser, ctx, page, console_, BASE, SHOTS, scanPhp };
}

// Sous quelle identité la recette tourne-t-elle RÉELLEMENT ? Rend le `user_login`
// connecté, ou `null`.
//
// 🚨 C'est la brique qui manquait pour que la Phase 21 soit trouvable : toute la
// recette se connecte en gérant, or `manage_woocommerce` satisfait TOUTES les
// gardes. Une porte destinée à un confirmateur pouvait donc verdir en ayant testé
// le gérant, sans que rien ne le signale.
//
// POURQUOI LE COOKIE, et pas ce qu'on essaie d'abord (mesuré le 2026-07-21) :
//   · `/wp-json/wp/v2/users/me` → **401** : l'auth par cookie exige un
//     `X-WP-Nonce` que la coque `/gestion` n'expose pas (son nonce `aod_cd` est un
//     nonce AJAX, inutilisable en REST) ;
//   · le DOM de `/gestion` montre le login, mais par accident — un `display_name`
//     distinct le masquerait. On lirait un libellé, pas une identité ;
//   · `window.AOD_CD` ne porte aucune clé d'identité, et l'y ajouter reviendrait à
//     modifier le PLUGIN pour satisfaire un TEST.
// Le cookie, lui, est émis par WordPress : c'est la réponse du serveur.
//
// 🚨 `HttpOnly` → invisible à `document.cookie`. D'où la signature en `ctx`.
//
// 🚨 ORDRE : décoder PUIS découper. La valeur est INTÉGRALEMENT encodée —
// séparateurs compris (`%7C`), espace en `%20`. Découper d'abord rendrait UN SEUL
// champ. Vérifié sur un login à espace : `jean%20dupont22%7C1784810356%7C…`.
async function whoAmI( ctx ) {
	const c = ( await ctx.cookies() ).find( ( x ) => x.name.startsWith( 'wordpress_logged_in_' ) );
	if ( ! c ) { return null; }
	const champs = decodeURIComponent( c.value ).split( '|' );
	// `login|expiration|token|hmac`. Autre chose = cookie malformé, pas une identité.
	return 4 === champs.length && champs[ 0 ] ? champs[ 0 ] : null;
}

// Connexion. `login( page )` garde le compte de `.env` — les 10 portes existantes
// n'ont pas bougé d'une ligne.
//
// 🚨 PURGE INCONDITIONNELLE, et c'est délibéré. L'ancienne version commençait par
// « si l'URL n'est plus /wp-login, la session est ouverte → on rend la main ».
// Deux défauts :
//   1. la condition ne se déclenchait JAMAIS (`/wp-login.php` affiche son
//      formulaire même connecté ; `login_redirect` n'agit qu'APRÈS une connexion) ;
//   2. elle raisonnait sur l'URL, jamais sur QUI est connecté.
// Et la remplacer par « si l'identité courante est la bonne, rendre la main »
// aurait MENTI : un cookie survit à `wp_destroy_all_sessions()`, à un changement de
// mot de passe et à l'expiration — on aurait rendu la main sans être connecté.
// Purger coûte zéro (les contextes sont neufs) et rend la bascule de rôle vraie
// par construction.
async function login( page, { user = USER, pass = PASS } = {} ) {
	const ctx = page.context(); // `login()` ne reçoit que `page` : ne PAS changer sa signature.
	await ctx.clearCookies();
	if ( ! pass ) {
		console.error( `❌ Mot de passe inconnu pour « ${ user } » : renseigne WP_ADMIN_PASSWORD dans .env (ou AOD_PASS), ou passe { pass } à login().` );
		process.exit( 2 );
	}
	await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', user );
	await page.fill( '#user_pass', pass );
	// 🚨 SI LA NAVIGATION N'ABOUTIT PAS, DIRE CE QU'ON A VU (D16).
	//
	// La porte `confirmateur` a flanché ici UNE fois, au 15ᵉ rang d'un `run.sh tout`,
	// sur « waitForNavigation: Timeout 30000ms ». Non reproductible : verte seule, et
	// verte au run complet suivant. Le rapport ne disait alors RIEN d'exploitable —
	// une exception Playwright nue, sans URL, sans état de page.
	//
	// ❌ Deux hypothèses ÉCARTÉES PAR LA MESURE, pour que personne ne les refasse :
	//   · « `load` attend les sous-ressources » → mesuré : `domcontentloaded` 220 ms,
	//     `load` 256 ms. **36 ms d'écart.** Passer en `domcontentloaded` n'aurait rien
	//     réglé — c'est pourquoi ce n'est PAS fait ici.
	//   · « le cron sortant bloque la requête » → `aod_ship_tracking_sync` s'exécute
	//     en 2,2 s ; l'option `aod_cod_pixels` est absente (l'envoi différé des
	//     conversions ne part nulle part) ; et le bloc `dns:` est bien présent, donc
	//     aucun appel ne pend sur une résolution.
	//
	// Reste une contention transitoire de la pile, qu'on ne saura diagnostiquer qu'AU
	// MOMENT où elle se reproduit. D'où ce bloc : il ne masque rien, il ne réessaie
	// pas — il **fait parler l'échec**.
	try {
		await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );
	} catch ( e ) {
		// 🚨 La capture peut échouer elle-même — c'est le cas quand la page est
		// justement bloquée, donc le cas le plus probable ici. Ne l'annoncer QUE si
		// elle a bien été écrite : un diagnostic qui renvoie vers une preuve
		// inexistante fait perdre plus de temps qu'un diagnostic muet. (Trouvé en
		// éprouvant ce bloc au rouge : la première version imprimait le chemin
		// inconditionnellement.)
		const shot = `${ SHOTS }/echec-login-${ user }.png`;
		let etat  = '(illisible)';
		let prise = false;
		try {
			await page.screenshot( { path: shot, fullPage: false, timeout: 5000 } );
			prise = true;
		} catch ( e2 ) { /* page bloquée : on continue sans capture. */ }
		try {
			etat = `url=${ page.url() } · titre=${ JSON.stringify( ( await page.title() ) || '' ) }`;
		} catch ( e3 ) {
			etat = 'page injoignable (elle ne répond plus au moment du diagnostic)';
		}
		console.error( `❌ Connexion « ${ user } » : la navigation n’a pas abouti.` );
		console.error( `   ${ e && e.message ? e.message.split( '\n' )[ 0 ] : e }` );
		console.error( `   État au moment de l’échec : ${ etat }` );
		console.error( prise ? `   Capture : ${ shot }` : '   Capture : impossible (la page ne répondait plus).' );
		console.error( '   Cf. ROADMAP §C.5 (D16) — hypothèses déjà écartées par la mesure.' );
		process.exit( 2 );
	}
	if ( page.url().includes( 'wp-login' ) ) {
		console.error( `❌ Connexion refusée pour « ${ user } » — vérifie le compte (WP_ADMIN_USER / WP_ADMIN_PASSWORD dans .env pour le compte par défaut).` );
		process.exit( 2 );
	}

	// TÉMOIN D'IDENTITÉ (DoD §C.2, règle du témoin) : une porte doit dire non
	// seulement ce qu'elle a inspecté, mais SOUS QUI.
	const qui = await whoAmI( ctx );
	console.log( `  ⓘ identité : ${ qui || '(inconnue)' }` );

	// 🚨 Comparaison tolérante, sinon on casse des clones qui marchent : WordPress
	// accepte la connexion PAR E-MAIL et compare le login SANS LA CASSE. Un `.env`
	// portant `Admin` ou une adresse e-mail ferait sortir les 10 portes en 2.
	if ( user.includes( '@' ) ) {
		if ( ! qui ) {
			console.error( '❌ Connexion par e-mail : aucune identité lisible après connexion.' );
			process.exit( 2 );
		}
		return qui;
	}
	if ( ! qui || qui.toLowerCase() !== user.toLowerCase() ) {
		console.error( `❌ Identité inattendue : connecté en « ${ qui || 'personne' } » alors que « ${ user } » était demandé.` );
		console.error( '   La recette refuse de continuer : elle testerait le mauvais utilisateur sans le dire.' );
		process.exit( 2 );
	}
	return qui;
}

const ok = ( c, m, extra = '' ) => {
	console.log( `${ c ? '  ✅' : '  ❌' } ${ m }${ extra ? ' — ' + extra : '' }` );
	return !! c; // Doit renvoyer la condition : les compteurs d'échecs s'en servent.
};

// Purge CIBLÉE des verrous anti-flood du formulaire COD, à passer à `wp eval`.
//
// 🚨 Remplace un `wp transient delete --all` que trois portes exécutaient. Elles
// ne veulent lever que deux verrous — le quota par IP (`aod_cod_ipf_<md5>`,
// AOD_COD_MAX_PER_IP_HOUR/heure) et le verrou 24 h par téléphone
// (`aod_cod_ph_<md5>`) — mais elles vidaient TOUS les transients du site au
// passage : caches de tarifs et de communes des transporteurs (Yalidine, Noest,
// Ecotrack, Maystro… 12 h à 1 semaine de TTL, reconstruits par des appels API
// externes), caches de stats de /gestion, et tout ce qu'un plugin tiers du clone
// y range. Rien d'irrécupérable, mais aucune raison de le faire : on ne casse
// que ce qu'on doit casser.
const PURGE_VERROUS_COD = `
global $wpdb; $n = 0;
foreach ( array( 'aod_cod_ipf_', 'aod_cod_ph_' ) as $prefixe ) {
	$noms = $wpdb->get_col( $wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_' . $prefixe ) . '%'
	) );
	foreach ( $noms as $nom ) { delete_transient( substr( $nom, strlen( '_transient_' ) ) ); $n++; }
}
echo $n;`.replace( /\n/g, ' ' );

// Les 12 sections de menu de /gestion — la liste de référence de toutes les
// recettes (a11y-contraste, a11y-noms, rtl-mobile la balaient).
//
// 🚨 Cette liste contenait `'stats'`, un slug SUPPRIMÉ en Phase 2 (renommé
// `home`), et omettait `home` et `abandoned`. Le routeur ne fait pas d'erreur sur
// un slug inconnu : `maybe_render()` retombe silencieusement sur la première
// section autorisée (`class-aod-cd-dashboard.php:327-329`). Conséquences, dans
// l'ordre de gravité :
//   1. `abandoned` n'était JAMAIS visitée — trou de couverture réel.
//   2. `/gestion/stats` rendait `home` (première section visible d'un gérant) :
//      l'Accueil était donc bien balayé, mais sous un nom qui n'existe pas. Un
//      échec de contraste sur les 18 KPI aurait été rapporté sur « stats », page
//      introuvable pour qui la cherche.
// Un balayage qui se trompe de page ne le dit pas : il compte quand même.
//
// `landing` est routable mais volontairement ABSENTE : elle exige `?produit=<id>`
// (sans lui, elle rend l'état vide « Produit introuvable »). Elle a sa propre
// porte — `tools/recette/landing-produit.js`.
const SECTIONS = [
	'home', 'orders', 'abandoned', 'products', 'stock', 'categories',
	'shipping', 'tracking', 'marketing', 'whatsapp', 'team', 'account',
];

/**
 * Slug de la section RÉELLEMENT rendue (l'entrée active de la barre latérale).
 *
 * 🚨 POURQUOI CE CONTRÔLE EST INDISPENSABLE (31.5). Le routeur de `/gestion`
 * n'émet ni 404 ni erreur sur un slug inconnu : il retombe sur la première
 * section visible (`class-aod-cd-dashboard.php` — « Section demandée si elle est
 * autorisée, sinon la première autorisée »). Mesuré : `/gestion/section-bidon`
 * rend `home`, avec `body.aod-cd` bien présent.
 *
 * Conséquence pour une porte qui visite N sections en boucle : elle peut rendre
 * **N fois `home` en imprimant N ✅**. Tous ses contrôles passent — la racine est
 * là, aucune erreur PHP, aucun débordement — mais elle n'a audité qu'une seule
 * page. C'est très exactement le filet de la Phase 30 (décomposition en sections
 * `AOD_CD_Section_*`) : une section extraite qui ne se route plus est INVISIBLE
 * pour une porte qui ne vérifie pas ce qu'elle regarde.
 *
 * ⚠️ Le piège a DÉJÀ mordu, cf. le commentaire de `SECTIONS` ci-dessus :
 * `/gestion/stats` rendait `home`, et le balayage comptait quand même.
 *
 * On lit le HREF de l'entrée active, jamais son libellé — il est traduit, donc
 * il diffère en AR et casserait la comparaison.
 *
 * @returns {Promise<string|null>}
 */
const sectionRendue = ( page ) => page.evaluate( () => {
	const a = document.querySelector( '.aod-cd-navlink.is-active' );
	return a ? ( a.getAttribute( 'href' ) || '' ).replace( /\/+$/, '' ).split( '/' ).pop() : null;
} );

/**
 * La section affichée porte-t-elle des DONNÉES, ou un état vide ? (dette D29)
 *
 * 🚨 CE QUE CE HELPER EXISTE POUR RENDRE VISIBLE. `sectionRendue()` répond à
 * « ai-je bien ouvert la bonne page ? ». Il ne répond pas à « y avait-il quelque
 * chose à auditer dessus ? » — et c'est l'angle mort qui a laissé `run.sh rtl`
 * VERTE pendant que `/gestion/orders` débordait de 193 px : mesuré le
 * 2026-07-28, **6 des 12 sections rendent un état vide** sur un clone de
 * développement (0 tableau, 0 ligne). Les portes transverses auditaient donc
 * des pages quasi nues — ni tableau, ni barre d'action, ni sélecteur — et
 * rendaient le MÊME vert qu'un audit exhaustif. C'est la « règle du témoin »
 * appliquée aux DONNÉES et non plus au code.
 *
 * Helper PARTAGÉ à dessein : la même question posée dans trois portes finirait
 * par recevoir trois réponses (leçon 31.5 — « un raisonnement écrit dans un
 * fichier ne protège pas les autres »).
 *
 * @param {import('playwright-core').Page} page
 * @returns {Promise<{lignes:number,vides:number,creuse:boolean}>}
 */
async function etatDonnees( page ) {
	return page.evaluate( () => {
		const lignes = document.querySelectorAll( 'body.aod-cd tbody tr' ).length;
		const vides = document.querySelectorAll( 'body.aod-cd .aod-cd-empty' ).length;
		// « Creuse » = aucune ligne ET un état vide affiché. Une section qui n'a
		// simplement pas de tableau (réglages : WhatsApp, Marketing, Compte…)
		// n'est PAS creuse : il n'y a rien à peupler, et la compter comme telle
		// noierait le signal utile.
		return { lignes, vides, creuse: 0 === lignes && vides > 0 };
	} );
}

/**
 * Gare le curseur SOMEWHERE D'INERTE, pour mesurer un état au repos et non un survol.
 *
 * 🚨 POURQUOI CE HELPER EXISTE — et pourquoi il ne fallait PAS le laisser en
 * constante recopiée. Six sites, dans deux portes, garaient la souris en
 * `(5, 5)` ou `(0, 0)` : le coin haut-gauche était inerte… jusqu'à l'étape 43.8,
 * qui en a fait la **zone de survol du rail** de la barre latérale. Depuis, y
 * poser le curseur DÉPLIE la barre à 248 px, laquelle recouvre alors la première
 * colonne des tableaux — et tout clic suivant y est intercepté. Symptôme
 * observé : `a11y-contraste` en échec sur les 6 accents, par timeout de 30 s sur
 * un `uncheck()`, sans que le message ne parle jamais de la sidebar.
 *
 * Le défaut n'était pas dans le produit mais dans une HYPOTHÈSE de la recette
 * (« le coin haut-gauche ne fait rien ») devenue fausse. Recopiée six fois, elle
 * a fallu six fois. D'où un helper unique : la prochaine évolution de la coque
 * n'aura qu'un endroit à corriger.
 *
 * Point choisi : le centre de la barre du haut (`.aod-cd-top`) — pleine largeur,
 * sans état de survol, présente sur toutes les pages, et à l'écart des DEUX
 * bords (le rail passe à droite en RTL, un coin n'est donc jamais neutre).
 *
 * @param {import('playwright-core').Page} page
 */
async function garerSouris( page ) {
	const pt = await page.evaluate( () => {
		const top = document.querySelector( '.aod-cd-top' );
		if ( top ) {
			const r = top.getBoundingClientRect();
			if ( r.width > 0 && r.height > 0 ) {
				return { x: Math.round( r.left + r.width / 2 ), y: Math.round( r.top + r.height / 2 ) };
			}
		}
		// Repli : milieu horizontal, tout en haut — jamais un coin.
		return { x: Math.round( document.documentElement.clientWidth / 2 ), y: 3 };
	} );
	await page.mouse.move( pt.x, pt.y );
}

/**
 * Cherche un témoin dans une liste PAGINÉE, page par page.
 *
 * 🚨 POURQUOI CE HELPER EXISTE — dette D31, et c'est la deuxième fois que ce
 * défaut mord dans la même semaine. Une assertion du type « après avoir vidé le
 * filtre, le témoin redevient visible » ne regarde, sans lui, que la PREMIÈRE
 * page. Or les listes de `/gestion` paginent à 20 et trient par date
 * décroissante, et les témoins de filtre par date sont datés de 2024 — donc
 * TOUJOURS les derniers. Il suffit que la boutique porte 20 lignes plus
 * récentes pour que l'assertion rougisse SANS le moindre défaut de produit.
 *
 * C'est exactement ce qui est arrivé : mesuré sur `abandonnees`, 28 lignes pour
 * un `PER_PAGE` de 20, témoin au rang 28 — page 2. Le filtre était bien vidé
 * (URL nue, champs vides, témoin présent en page 2) : la porte accusait le
 * produit d'un défaut qui était le sien.
 *
 * 🚨 ET LA LEÇON EST CELLE DE `97e8982`, QUI N'AVAIT PAS ÉTÉ PROPAGÉE. Le même
 * jour, le même défaut avait été corrigé sur le cliquet `test-filtre-date.php`
 * — « un test qui ne survit pas aux données réelles de la zone qu'il garde ne
 * garde plus rien » — et le correctif n'avait pas atteint son jumeau, la porte.
 * D'où UN SEUL CORPS ici pour ses DEUX appelants (`abandonnees.js`,
 * `commandes-suivi.js`) : deux copies auraient rouvert la divergence.
 *
 * On compte des LIGNES DE TABLEAU, jamais `has( html, … )` : le champ de
 * recherche réaffiche sa propre valeur (`value="…"`), donc un grep du HTML brut
 * serait vrai même quand la liste est vide.
 *
 * @param {import('playwright-core').Page} page  Page Playwright déjà connectée.
 * @param {string} url                           URL de la liste, SANS `paged`.
 * @param {string} texte                         Texte du témoin cherché.
 * @param {{max?: number}} [opts]                Garde-fou d'itérations.
 * @return {Promise<{trouve: boolean, page: number, pages: number}>}
 */
async function chercheDansPages( page, url, texte, { max = 30 } = {} ) {
	const compteIci = () => page.locator( 'tr' ).filter( { hasText: texte } ).count();

	await page.goto( url, { waitUntil: 'networkidle' } );

	// Nombre de pages : la barre de pagination expose TOUJOURS un lien vers la
	// dernière (fenêtre glissante + raccourci « … N »). Absente ⇒ une seule page.
	const pages = await page.evaluate( () => {
		const liens = Array.from( document.querySelectorAll( '.aod-cd-pagination .aod-cd-page' ) )
			.map( ( a ) => parseInt( a.textContent.trim(), 10 ) )
			.filter( ( n ) => ! isNaN( n ) );
		return liens.length ? Math.max( ...liens ) : 1;
	} );

	if ( await compteIci() > 0 ) {
		return { trouve: true, page: 1, pages };
	}

	const fin = Math.min( pages, max );
	for ( let n = 2; n <= fin; n++ ) {
		const u = new URL( url );
		u.searchParams.set( 'paged', String( n ) );
		await page.goto( u.toString(), { waitUntil: 'networkidle' } );
		if ( await compteIci() > 0 ) {
			return { trouve: true, page: n, pages };
		}
	}

	return { trouve: false, page: 0, pages };
}

/**
 * Confirme la QUESTION MAISON qui vient de s'ouvrir (Phase 47.7).
 *
 * 🚨 REMPLACE `page.on( 'dialog', d => d.accept() )`. Les 13 dialogues natifs
 * ont disparu en 47.7 : Playwright n'a donc plus rien à accepter, et les portes
 * qui posaient ce gestionnaire voyaient leur action ne jamais partir — la
 * fenêtre restait ouverte, personne ne cliquant « Confirmer ». Rouge légitime,
 * pas régression produit.
 *
 * ⚠️ `:not(#aod-cd-modal)` — la modale PARTAGÉE de la 47.4 est un markup
 * PERMANENT, masqué mais présent dans le DOM : sans cette exclusion on
 * chercherait un bouton dans une fenêtre qui n'est pas la bonne.
 *
 * @param {import('playwright-core').Page} page
 * @param {number} timeout ms d'attente de la fenêtre (défaut 5000).
 */
async function confirmerMaison( page, timeout = 5000 ) {
	const sel = '.aod-cd-modal:not(#aod-cd-modal) [data-ok]';
	await page.waitForSelector( sel, { timeout } );
	await page.locator( sel ).last().click();
	await page.waitForTimeout( 400 );
}

module.exports = { launch, login, whoAmI, scanPhp, ok, BASE, SHOTS, SECTIONS, PURGE_VERROUS_COD, sectionRendue, etatDonnees, garerSouris, chercheDansPages, confirmerMaison };
