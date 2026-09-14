<?php
/**
 * Un binder de /gestion qui casse ne disparaît plus en silence (Phase 32.5, D24).
 *
 * 🚨 LE DÉFAUT. `dashboard.js` initialisait 30 binders : 19 sous un `try/catch`
 * au corps VIDE (l'exception avalée sans un mot), 11 sans protection du tout —
 * une exception dans l'un tuait le handler DOMContentLoaded et TOUS les binders
 * suivants (`bindStatus` cassé = plus de suivi, plus d'équipe, plus de page de
 * vente). Dans les deux cas le symptôme est le même : un bouton qui ne répond
 * plus, SANS erreur console, sans trace, sans cause visible. C'est le pendant
 * navigateur de la 31.3 : rendre l'échec visible sans changer le nominal.
 * ⚠️ L'énoncé de la dette disait « 16 catch sur 27 » — mesuré : 19 sur 30.
 * Un énoncé de story n'est pas une mesure (même leçon qu'en 32.2 et 32.3).
 *
 * LE CORRECTIF : un helper unique `init( name, fn )` qui isole chaque binder ET
 * journalise en console le NOM du fautif. Console et non toast, à dessein :
 * c'est un défaut de développement, pas un message pour le gérant.
 *
 * 🚨 R4 EST LA RÈGLE QUI PROTÈGE L'ACHETEUR. Le réflexe du catch muet se
 * recopie — il s'était DÉJÀ recopié côté public : 3 `catch ( e ) {}` dans
 * `aod-cod-form.js`. Ces trois-là sont VOULUS et restent : chacun isole un
 * appel TIERS (Turnstile, gtag, flexslider) autour du chemin d'achat, où le
 * silence est la bonne politique — l'acheteur ne doit jamais voir un détail
 * technique, et chaque site a soit un repli explicite (flexslider simule un
 * clic natif), soit un no-op inoffensif (un jeton Turnstile frais sera
 * redemandé au prochain rendu). R4 les recense AVEC leur raison et refuse tout
 * NOUVEAU catch muet public : un de plus devra soit récupérer explicitement,
 * soit inscrire sa raison ici. Une exemption sans raison écrite est un échec.
 *
 * ⚠️ Test STATIQUE : il prouve la présence du câblage (motifs dans la source),
 * pas l'exécution. La preuve fonctionnelle (page chargée, zéro erreur console)
 * est faite au navigateur — portes `rtl` et contrôle console de la story.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Binders 32.5 — R0 : témoins' );

// Depuis la 33.1, le JS de /gestion est MULTI-FICHIERS : la coque dashboard.js
// + les modules section-*.js. Tout le périmètre est balayé, concaténé — un
// catch muet dans un module serait exactement le même trou que dans la coque.
$aod_ib_src      = '';
$aod_ib_fichiers = glob( AOD_CD_PATH . 'assets/js/*.js' );
foreach ( $aod_ib_fichiers as $aod_ib_f ) {
	$aod_ib_src .= (string) file_get_contents( $aod_ib_f ) . "\n";
}
AOD_Test::ok(
	strlen( $aod_ib_src ) > 10000 && count( $aod_ib_fichiers ) >= 2,
	sprintf( 'TÉMOIN : %d fichiers JS de /gestion lus (%d Ko)', count( $aod_ib_fichiers ), (int) round( strlen( $aod_ib_src ) / 1024 ) ),
	'fichiers illisibles ou vides : tout le reste verdirait sur du vide'
);

if ( '' === $aod_ib_src ) {
	return;
}

/**
 * Corps de `catch` VIDES (espaces et commentaires seuls) d'une source JS.
 *
 * ⚠️ Un corps borné à `[^}]*` : un catch dont le corps contient un bloc `{ }`
 * imbriqué n'est pas capturé — acceptable, un tel corps n'est pas vide.
 *
 * @param string $src
 * @return string[] Extraits des catch muets trouvés.
 */
function aod_ib_catch_muets( $src ) {
	$muets = array();
	if ( preg_match_all( '/catch\s*\(\s*\w+\s*\)\s*\{([^}]*)\}/', $src, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $hit ) {
			$corps = preg_replace( '#/\*.*?\*/#s', '', $hit[1] );
			$corps = preg_replace( '#//[^\n]*#', '', $corps );
			if ( '' === trim( (string) $corps ) ) {
				$muets[] = trim( preg_replace( '/\s+/', ' ', $hit[0] ) );
			}
		}
	}
	return $muets;
}

/* =========================================================================
 * R1 — Plus AUCUN catch muet dans dashboard.js
 * ========================================================================= */

AOD_Test::group( 'Binders 32.5 — R1 : plus un seul catch muet dans /gestion' );

$aod_ib_muets = aod_ib_catch_muets( $aod_ib_src );
AOD_Test::ok(
	empty( $aod_ib_muets ),
	'🔴 le JS de /gestion (coque + modules) ne contient plus aucun catch au corps vide',
	implode( ' · ', $aod_ib_muets ) . ' — un binder cassé redeviendrait un bouton mort sans cause visible'
);

// ANCRAGE : les catch LÉGITIMES (repli explicite) n'ont pas été « nettoyés »
// par excès de zèle — trois replis de parsing/données doivent survivre.
$aod_ib_replis = preg_match_all( '/catch\s*\(\s*\w+\s*\)\s*\{\s*(?:map|catalog)\s*=\s*\{\}|catch\s*\(\s*\w+\s*\)\s*\{\s*return;/', $aod_ib_src, $aod_ib_r );
AOD_Test::ok(
	$aod_ib_replis >= 3,
	sprintf( 'ANCRAGE : les replis explicites (JSON.parse, points de graphe) sont intacts (%d)', $aod_ib_replis ),
	'un repli avec récupération n’est pas un silence — le supprimer casserait la récupération'
);

/* =========================================================================
 * R2 — Chaque initialiseur passe par init(), qui nomme le fautif
 *
 * 🚨 C'est la règle qui tiendra dans le temps : un binder AJOUTÉ demain sans
 * passer par init() referait le trou — déclaré ici, il échoue.
 * ========================================================================= */

AOD_Test::group( 'Binders 32.5 — R2 : tout initialiseur passe par init()' );

// Les initialiseurs = les `function bindX()` SANS paramètre. Les binders
// paramétrés (bindFloatingMenu, bindBlock…) sont des sous-binders appelés par
// élément — hors périmètre, et c'est voulu.
preg_match_all( '/function (bind\w+)\(\s*\)/', $aod_ib_src, $aod_ib_m );
$aod_ib_binders = array_unique( $aod_ib_m[1] );

AOD_Test::ok(
	count( $aod_ib_binders ) >= 25,
	sprintf( 'TÉMOIN : %d initialiseurs sans argument recensés', count( $aod_ib_binders ) ),
	'si le motif de déclaration a changé, ce test ne regarde plus rien'
);

// Deux câblages nomment le fautif : `init( 'x', x )` (direct, ou re-init
// dynamique comme bindOptions dans bindProducts) et `register( 'x', x )` (le
// registre de la 33.1, dont le runner passe par init()). Un binder appelé NU
// reste un trou.
$aod_ib_orphelins = array();
foreach ( $aod_ib_binders as $aod_ib_b ) {
	$aod_ib_via_init = false !== strpos( $aod_ib_src, "init( '" . $aod_ib_b . "', " . $aod_ib_b . " )" );
	$aod_ib_via_reg  = false !== strpos( $aod_ib_src, "register( '" . $aod_ib_b . "', " . $aod_ib_b . " )" );
	if ( ! $aod_ib_via_init && ! $aod_ib_via_reg ) {
		$aod_ib_orphelins[] = $aod_ib_b;
	}
}
AOD_Test::ok(
	empty( $aod_ib_orphelins ),
	'🔴 Chaque initialiseur est enregistré via init() ou register() — le NOM du fautif sera dans la console',
	implode( ', ', $aod_ib_orphelins ) . ' — appelé nu ou sous un try muet, son échec serait de nouveau invisible'
);

// Le helper existe UNE fois et journalise le nom en console.error.
AOD_Test::ok(
	1 === substr_count( $aod_ib_src, 'function init( name, fn )' ),
	'Le helper init() est défini une seule fois'
);
AOD_Test::ok(
	false !== strpos( $aod_ib_src, "console.error( '[aod-cd] init « ' + name" ),
	'…et il nomme le binder fautif dans la console',
	'un catch qui ne dit pas QUI a échoué renvoie le diagnostic à la fouille ligne à ligne'
);

/* =========================================================================
 * R4 — Côté ACHETEUR, le silence reste la politique — mais il est RECENSÉ
 * ========================================================================= */

AOD_Test::group( 'Binders 32.5 — R4 : les catch muets publics sont recensés, pas libres' );

// Exemptions déclarées AVEC leur raison. Une nouvelle entrée exige sa raison ici.
$aod_ib_exemptions = array(
	'aod-cod-form.js'    => array(
		'attendus' => 4,
		'raison'   => 'Turnstile.reset (jeton frais redemandé au prochain rendu) · gtag (le marketing ne casse jamais le chemin d’achat, cf. Phase 14) · flexslider (repli : clic simulé sur la navigation native) · sessionStorage.setItem (2026-09-14, redirection post-commande vers la Boutique) : en navigation privée ou stockage plein, écrire lève — la commande est déjà créée et la redirection a lieu quoi qu’il arrive (hors du try), seul le bandeau de remerciement manquerait alors à l’arrivée, jamais bloquant.',
	),
	'aod-cod-contact.js' => array(
		'attendus' => 0,
		'raison'   => '—',
	),
	'aod-cart.js'        => array(
		'attendus' => 2,
		'raison'   => 'localStorage.setItem (35.3) : en navigation privée ou stockage plein, écrire lève — le panier redevient alors VOLATIL (l’état repart de la page), ce qui est le comportement voulu sur le chemin d’achat : jamais une erreur visible pour un confort de persistance. La lecture, elle, récupère explicitement (retour []). · URLSearchParams (marqueur de validation du panier, 2026-09-13) : un navigateur trop ancien pour le supporter retombe simplement sur la vue panier+fiche habituelle — jamais bloquant, jamais visible.',
	),
);

foreach ( glob( AOD_COD_PATH . 'assets/js/*.js' ) as $aod_ib_f ) {
	$aod_ib_nom   = basename( $aod_ib_f );
	$aod_ib_muets = aod_ib_catch_muets( (string) file_get_contents( $aod_ib_f ) );
	$aod_ib_att   = isset( $aod_ib_exemptions[ $aod_ib_nom ] ) ? $aod_ib_exemptions[ $aod_ib_nom ]['attendus'] : 0;
	AOD_Test::ok(
		count( $aod_ib_muets ) === $aod_ib_att,
		sprintf( '%s : %d catch muet(s), %d recensé(s)', $aod_ib_nom, count( $aod_ib_muets ), $aod_ib_att ),
		count( $aod_ib_muets ) > $aod_ib_att
			? 'un NOUVEAU catch muet côté acheteur : récupérer explicitement, ou inscrire sa raison dans la table de ce test'
			: 'un catch recensé a disparu : mettre la table à jour (et vérifier que la protection du chemin d’achat n’est pas partie avec)'
	);
}
