<?php
/**
 * Cliquet sur les couches anti-bot — une couche annoncée doit être opérante.
 *
 * CE QUE CE TEST REMPLACE. `TEMPLATE.md` annonce un « anti-bot **5 couches** ».
 * Deux d'entre elles ne tenaient pas, et rien ne le disait — la forme exacte du
 * défaut que ce dépôt traque : **un contrôle qui annonce une couverture qu'il
 * n'a pas, sans jamais échouer.**
 *
 * 🔴 COUCHE 3 (rate-limit IP) — elle comptait par EN-TÊTE, pas par visiteur.
 * Le compteur était indexé sur `WC_Geolocation::get_ip_address()`, qui renvoie
 * `X-Real-IP` **brut** (`class-wc-geolocation.php:83-84`) — sans même le
 * `rest_is_ip_address()` que ses deux branches suivantes appliquent. Un en-tête
 * est écrit par le client : `X-Real-IP: <aléatoire>` à chaque requête donnait une
 * clé `md5()` neuve à chaque fois, et le plafond n'était jamais atteint.
 * → Ce n'est pas un bug de WooCommerce. `get_ip_address()` sert à deviner un pays
 *   pour pré-remplir un formulaire — un visiteur qui s'y ment ne nuit qu'à lui.
 *   **Le défaut était de réutiliser une source de CONFORT comme source de
 *   SÉCURITÉ.** C'est la leçon à retenir, plus que la ligne corrigée.
 *
 * 🔴 COUCHE 4 (Turnstile) — fail-open **sans borne, sans trace, sans fin**.
 * `verify()` retournait « valide » dès que Cloudflare était injoignable. Trois
 * conséquences qu'aucun écran ne révélait : le contournement coûtait UN caractère
 * (`cf_turnstile=x` franchit le test « jeton vide », déclenche l'appel, le voit
 * échouer, passe) ; sur un clone **sans réseau sortant** — le cas que TEMPLATE.md
 * §3 pose comme NOMINAL — l'échec est permanent, donc la couche est ouverte en
 * permanence, pas seulement pendant une panne ; et la page de réglages affichait
 * « ✅ Actif » pendant ce temps. **Le défaut n'était pas la politique, c'était son
 * silence.**
 *
 * ⚠️ CE QUE CE TEST NE PROUVE PAS. Il n'envoie aucune requête HTTP forgée : il
 * éprouve la SOURCE d'IP et la DÉCISION de fail-open, pas la chaîne complète.
 * Une porte de recette navigateur reste le seul contrôle de bout en bout.
 * ⚠️ Il ne teste pas non plus l'appel réseau à Cloudflare (il dépendrait d'un
 * service tiers, donc rougirait sans défaut). Ce sont `health()` et
 * `max_failopen()` — la logique décisionnelle — qui sont éprouvées.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * Couche 3 — la source d'IP ne doit RIEN accepter du client
 * ========================================================================= */

AOD_Test::group( 'Anti-bot 26.1 — R1 : la source d’IP est REMOTE_ADDR, jamais un en-tête' );

AOD_Test::ok(
	class_exists( 'AOD_COD_IP' ),
	'AOD_COD_IP est chargée (source d’IP unique du plugin)',
	'Sans elle, les coupe-circuits retombent sur WC_Geolocation — donc sur un en-tête forgeable.'
);

/**
 * Joue un cas de figure sur $_SERVER et rend l'IP retenue.
 *
 * ⚠️ Préfixe `aod_test_ab_` : `tests/all.php` charge tous les fichiers par
 * `glob()` dans le même espace global.
 *
 * @param array $serveur Entrées $_SERVER à poser.
 * @return string
 */
function aod_test_ab_ip( $serveur ) {
	$sauve = $_SERVER;
	unset( $_SERVER['HTTP_X_REAL_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR'] );
	foreach ( $serveur as $k => $v ) {
		$_SERVER[ $k ] = $v;
	}
	$ip      = AOD_COD_IP::client();
	$_SERVER = $sauve;
	return $ip;
}

$aod_ab_cas = array(
	array(
		'quoi'    => '🚨 X-Real-IP forgé est IGNORÉ — c’est la faille de la couche 3',
		'serveur' => array( 'REMOTE_ADDR' => '41.200.1.5', 'HTTP_X_REAL_IP' => 'forge-abc-123' ),
		'attendu' => '41.200.1.5',
		'pourquoi' => 'Si cet en-tête était retenu, chaque valeur ferait une clé de compteur neuve '
			. 'et le plafond anti-flood ne serait JAMAIS atteint. C’est le défaut d’origine.',
	),
	array(
		'quoi'    => 'X-Forwarded-For est ignoré tant qu’aucun proxy n’est déclaré',
		'serveur' => array( 'REMOTE_ADDR' => '41.200.1.5', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4' ),
		'attendu' => '41.200.1.5',
		'pourquoi' => 'Un en-tête de proxy n’a de valeur que si un proxy de confiance est déclaré.',
	),
	array(
		'quoi'    => 'REMOTE_ADDR nominal',
		'serveur' => array( 'REMOTE_ADDR' => '41.200.1.5' ),
		'attendu' => '41.200.1.5',
		'pourquoi' => 'Le cas courant doit passer sans réserve.',
	),
	array(
		'quoi'    => 'Le port est retiré (sinon deux clés pour un même visiteur)',
		'serveur' => array( 'REMOTE_ADDR' => '41.200.1.5:56789' ),
		'attendu' => '41.200.1.5',
		'pourquoi' => 'Un port qui varie à chaque connexion diviserait le compteur.',
	),
	array(
		'quoi'    => '🚨 Une valeur non-IP vaut « pas d’IP », JAMAIS une clé de plus',
		'serveur' => array( 'REMOTE_ADDR' => 'pas-une-ip' ),
		'attendu' => '',
		'pourquoi' => 'Sans validation, n’importe quelle chaîne devient une clé md5() distincte : '
			. 'le coupe-circuit ne compte plus rien. C’est le cœur du correctif.',
	),
	array(
		'quoi'    => 'IPv6 entre crochets, avec port',
		'serveur' => array( 'REMOTE_ADDR' => '[2001:db8::1]:443' ),
		'attendu' => '2001:db8::1',
		'pourquoi' => 'Forme courante derrière certains serveurs ; mal découpée, elle invaliderait l’IP.',
	),
);

foreach ( $aod_ab_cas as $aod_ab_c ) {
	AOD_Test::eq( $aod_ab_c['attendu'], aod_test_ab_ip( $aod_ab_c['serveur'] ), $aod_ab_c['quoi'] );
}

AOD_Test::ok(
	! AOD_COD_IP::trusts_proxy(),
	'Aucun proxy de confiance par défaut (il se déclare dans wp-config.php)',
	'Faire confiance aux en-têtes par défaut, c’est réintroduire la faille pour tous les clones.'
);

/* ---- Les deux gardes de sécurité n’utilisent plus la source de confort ---- */

AOD_Test::group( 'Anti-bot 26.1 — R2 : plus aucune garde ne lit WC_Geolocation' );

$aod_ab_racine = defined( 'AOD_COD_PATH' ) ? AOD_COD_PATH : dirname( AOD_CD_PATH ) . '/aod-cod-form/';
$aod_ab_gardes = array(
	'includes/class-aod-cod-form.php'    => 'coupe-circuit anti-flood des commandes COD',
	'includes/class-aod-cod-contact.php' => 'coupe-circuit anti-flood du formulaire de contact',
);

/**
 * Le CODE d'un fichier, commentaires retirés.
 *
 * 🚨 Pourquoi passer par `token_get_all()` plutôt que grepper le texte : la
 * première version de ce cliquet a rougi sur ses PROPRES correctifs. Les
 * commentaires qui expliquent la correction NOMMENT forcément `WC_Geolocation`
 * — c'est tout leur intérêt — et un grep de texte y voyait un appel.
 * → Un contrôle qui confond la prose et le code crie au loup sur la
 *   documentation, et pousse à écrire du code moins expliqué pour le faire taire.
 *
 * @param string $chemin Fichier PHP.
 * @return string
 */
function aod_test_ab_code( $chemin ) {
	if ( ! is_readable( $chemin ) ) {
		return '';
	}
	$out = '';
	foreach ( token_get_all( (string) file_get_contents( $chemin ) ) as $t ) {
		if ( is_array( $t ) ) {
			if ( T_COMMENT === $t[0] || T_DOC_COMMENT === $t[0] ) {
				continue;
			}
			$out .= $t[1];
			continue;
		}
		$out .= $t;
	}
	return $out;
}

foreach ( $aod_ab_gardes as $aod_ab_f => $aod_ab_role ) {
	$aod_ab_src = aod_test_ab_code( $aod_ab_racine . $aod_ab_f );

	AOD_Test::ok(
		'' !== $aod_ab_src,
		sprintf( 'Témoin : %s lu, commentaires retirés (%d octets de code)', $aod_ab_f, strlen( $aod_ab_src ) ),
		'Fichier illisible : les deux assertions suivantes verdiraient sur du vide.'
	);
	AOD_Test::ok(
		false !== strpos( $aod_ab_src, 'AOD_COD_IP::client()' ),
		sprintf( '%s prend son IP de AOD_COD_IP::client()', $aod_ab_role ),
		'La garde doit lire la source validée, pas une autre.'
	);
	AOD_Test::ok(
		false === strpos( $aod_ab_src, 'WC_Geolocation' ),
		sprintf( '%s ne lit plus WC_Geolocation', $aod_ab_role ),
		'🔴 WC_Geolocation::get_ip_address() renvoie X-Real-IP BRUT : un en-tête forgé y '
			. 'devient une IP. Source de confort (géolocalisation commerciale), jamais de sécurité. '
			. 'La rebrancher ici rouvre la faille de la couche 3.'
	);
}

/* =========================================================================
 * Couche 4 — le fail-open de Turnstile est borné, et il laisse une trace
 * ========================================================================= */

AOD_Test::group( 'Anti-bot 26.1 — R3 : le fail-open Turnstile est borné et visible' );

AOD_Test::ok(
	method_exists( 'AOD_COD_Turnstile', 'health' ) && method_exists( 'AOD_COD_Turnstile', 'max_failopen' ),
	'AOD_COD_Turnstile expose health() et max_failopen()',
	'Sans état lisible, la dégradation reste invisible pour le gérant — le défaut d’origine.'
);

$aod_ab_max = AOD_COD_Turnstile::max_failopen();

AOD_Test::ok(
	$aod_ab_max > 0,
	sprintf( 'Le seuil de tolérance est strictement positif (%d)', $aod_ab_max ),
	'Un seuil à 0 serait du fail-closed déguisé : une panne Cloudflare arrêterait toutes '
		. 'les ventes de tous les clones. Le choix retenu est « fail-open BORNÉ », pas « fermé ».'
);

/** Force un état de santé et rend le verdict correspondant. */
function aod_test_ab_sante( $fails ) {
	if ( $fails <= 0 ) {
		delete_transient( AOD_COD_Turnstile::HEALTH_TRANSIENT );
	} else {
		set_transient(
			AOD_COD_Turnstile::HEALTH_TRANSIENT,
			array( 'fails' => (int) $fails, 'last' => time(), 'cause' => 'reseau', 'detail' => 'test' ),
			AOD_COD_Turnstile::HEALTH_TTL
		);
	}
	return AOD_COD_Turnstile::health();
}

$aod_ab_sauve = get_transient( AOD_COD_Turnstile::HEALTH_TRANSIENT );

$aod_ab_sain = aod_test_ab_sante( 0 );
AOD_Test::ok( ! $aod_ab_sain['degraded'], 'Service sain : non dégradé', 'Un état neuf ne doit pas alerter.' );
AOD_Test::ok( ! $aod_ab_sain['strict'], 'Service sain : pas de bascule en strict', 'Aucune raison de rejeter.' );

$aod_ab_un = aod_test_ab_sante( 1 );
AOD_Test::ok(
	$aod_ab_un['degraded'],
	'🚨 Un SEUL échec suffit à marquer le service « dégradé »',
	'C’est le cœur du correctif : la protection ne protège plus DÈS le premier échec. '
		. 'Le gérant doit l’apprendre à ce moment-là, pas au 20ᵉ.'
);
AOD_Test::ok(
	! $aod_ab_un['strict'],
	'…mais la commande passe encore (fail-open borné, pas fermé)',
	'Un incident isolé ne doit pas coûter une vente.'
);

$aod_ab_pile = aod_test_ab_sante( $aod_ab_max );
AOD_Test::ok( ! $aod_ab_pile['strict'], sprintf( 'Au seuil exact (%d échecs) : on laisse encore passer', $aod_ab_max ), 'La borne est inclusive.' );

$aod_ab_trop = aod_test_ab_sante( $aod_ab_max + 1 );
AOD_Test::ok(
	$aod_ab_trop['strict'],
	sprintf( '🚨 Au-delà du seuil (%d échecs) : bascule en STRICT', $aod_ab_max + 1 ),
	'Au-delà, ce n’est plus un incident, c’est un ÉTAT — un clone sans réseau, une clé '
		. 'révoquée. Continuer à laisser passer indéfiniment, c’est le défaut d’origine.'
);

// Restaure l'état réel du site (le clone porte les données de l'owner).
if ( is_array( $aod_ab_sauve ) ) {
	set_transient( AOD_COD_Turnstile::HEALTH_TRANSIENT, $aod_ab_sauve, AOD_COD_Turnstile::HEALTH_TTL );
} else {
	delete_transient( AOD_COD_Turnstile::HEALTH_TRANSIENT );
}

/*
 * 🚨 CETTE ASSERTION ÉTAIT UNE DISJONCTION QUI MASQUAIT LES DEUX CAS (31.5).
 * Elle s'écrivait :
 *
 *     true === verify( '', '' )  ||  ! is_enabled()
 *
 * Sur un clone NEUF (Turnstile non configuré), `verify()` sort tôt en renvoyant
 * `true` : les deux membres sont vrais, et le `||` rendait le contrôle vide —
 * `! is_enabled()` suffisait à le valider, sans jamais éprouver `verify()`.
 *
 * Et sur un clone où Turnstile EST configuré, c'était pire qu'inutile : un jeton
 * vide y est correctement rejeté (`verify()` rend `false`), donc les deux membres
 * étaient faux et le test ÉCHOUAIT — en signalant un défaut là où la protection
 * faisait exactement son travail. Une même ligne ne prouvait rien dans un cas et
 * criait au loup dans l'autre.
 *
 * On distingue donc les deux états au lieu de les additionner.
 */
if ( AOD_COD_Turnstile::instance()->is_enabled() ) {
	AOD_Test::ok(
		false === AOD_COD_Turnstile::instance()->verify( '', '' ),
		'Challenge CONFIGURÉ sur ce clone : un jeton vide est rejeté (couche 4 active)',
		'Un POST direct sur admin-ajax sans jeton doit être refusé.'
	);
} else {
	AOD_Test::ok(
		true === AOD_COD_Turnstile::instance()->verify( '', '' ),
		'Challenge non configuré : verify() ne bloque rien (clone neuf)',
		'Un template sans clés ne doit pas empêcher de vendre. C’est voulu — mais c’est '
			. 'précisément pourquoi la couche 4 est ABSENTE par défaut, et pourquoi la 3 doit tenir seule.'
	);
}
