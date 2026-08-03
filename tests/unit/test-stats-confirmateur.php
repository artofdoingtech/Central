<?php
/**
 * Onglet Confirmation de l'Accueil : un confirmateur lit SES chiffres, pas ceux
 * de la boutique (Phase 47.5).
 *
 * 🚨 CE QUE CE CLIQUET GARDE, ET POURQUOI IL RENDAIT LE HTML PLUTÔT QUE DE LIRE
 * LA DONNÉE. Le défaut de la 47.5 n'était PAS dans `confirmation_data()` : cette
 * méthode est correcte, et elle DOIT rester globale (elle ramène volontairement
 * les lignes de tous les confirmateurs — cf. `test-assignation-accueil.php`,
 * 42.3bis). Le défaut était dans le RENDU, trois lignes plus loin : le `else` de
 * `tab_confirmation()` servait les agrégats de la boutique à un confirmateur.
 *
 * Un cliquet qui interrogerait la donnée serait donc VERT sur le défaut. Il faut
 * rendre l'onglet — `ob_start()` + réflexion — sous trois identités réelles, et
 * lire les nombres qui sortent. C'est le patron des 47.3/47.4 : une règle qui
 * PROVOQUE l'état ne peut pas être satisfaite par la description de l'état.
 *
 * ⚠️ LES DEUX PIÈGES DU HARNAIS, tous deux déjà payés en 42.3bis :
 *   1. `user_scope()`/`assignment_scope()` sont mémoïsées PAR INSTANCE sur le
 *      singleton → reset par réflexion entre chaque utilisateur simulé.
 *   2. `confirmation_data()` passe par `cached()` (transient, TTL 5 min) dont la
 *      clé porte plage + produit + PÉRIMÈTRE PRODUITS — **pas l'identité**. Deux
 *      utilisateurs au même périmètre partagent donc le cache, et c'est VOULU
 *      (la donnée ne dépend pas du lecteur). On sème AVANT le premier appel ;
 *      `woocommerce_new_order` bump le bac du mois concerné, ce qui invalide de
 *      lui-même le transient d'une exécution précédente.
 *
 * ⚠️ AUCUN NOMBRE N'EST ÉCRIT EN DUR. Les attendus sont DÉRIVÉS de la donnée
 * réellement agrégée : un clone dont la fenêtre témoin ne serait pas vide reste
 * couvert. R0 vérifie que les nombres dérivés sont bien DISTINCTS et non nuls —
 * sur des nombres égaux, R2 serait vraie par accident.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Stats confirmateur 47.5 — R0 : socle, identités, décor' );

$aod_sc_socle = class_exists( 'AOD_CD_Section_Home' ) && class_exists( 'AOD_CD_Roles' ) && class_exists( 'WC_Order' );
AOD_Test::ok( $aod_sc_socle, 'Socle présent (AOD_CD_Section_Home + AOD_CD_Roles + WooCommerce)' );

if ( ! $aod_sc_socle ) {
	return;
}

$aod_sc_user_avant = get_current_user_id();
$aod_sc_home       = AOD_CD_Section_Home::instance();

/** Reset de la mémoïsation par instance (patron `test-assignation-accueil.php:37-45`). */
$aod_sc_reset = function ( $obj ) {
	$ref = new ReflectionObject( $obj );
	foreach ( array( 'user_scope', 'assignment_scope' ) as $prop ) {
		if ( $ref->hasProperty( $prop ) ) {
			$rp = $ref->getProperty( $prop );
			$rp->setAccessible( true );
			$rp->setValue( $obj, null );
		}
	}
};

/**
 * Confirmateur porteur de `aod_cap_stats_confirm` — la cap qui ouvre l'onglet.
 *
 * ⚠️ `$confirme` ajoute `aod_cap_confirm_orders`, et ce n'est PAS décoratif :
 * c'est cette cap qui arme `assignment_scope()`. Sans elle, R1 serait INCAPABLE
 * de voir la mutation qu'elle est censée garder — scoper `confirmation_data()`
 * par assignation resterait sans effet sur un témoin dont le scope vaut 0, et le
 * cliquet resterait vert sur le geste que D5 interdit. Mesuré en écrivant le
 * red-proof de la 47.5.
 */
$aod_sc_make_conf = function ( $login, $confirme = true ) {
	$uid = wp_insert_user( array(
		'user_login'   => $login,
		'user_pass'    => wp_generate_password( 24 ),
		'user_email'   => $login . '@example.invalid',
		'display_name' => 'T47.5 ' . $login,
		'role'         => AOD_CD_ROLE_CONFIRMER,
	) );
	if ( is_wp_error( $uid ) ) {
		return 0;
	}
	$user = new WP_User( (int) $uid );
	$user->add_cap( 'aod_cap_stats_confirm' );
	if ( $confirme ) {
		$user->add_cap( 'aod_cap_confirm_orders' );
	}
	return (int) $uid;
};

/**
 * Commande jetable, datée dans le passé, éventuellement tracée.
 *
 * ⚠️ `_aod_confirmed_at` est en heure LOCALE du site : `wp_date()`, jamais
 * `gmdate()`. Un semis en UTC brut donnerait un `$delta` négatif, que
 * `confirmation_data()` exclut EN SILENCE (`:1829`) — `delay_n` retomberait à 0
 * et les règles de délai verdiraient sur du vide.
 */
$aod_sc_make_order = function ( $created_ts, $status, $confirmed_by = 0, $confirmed_after = 0 ) {
	$order = new WC_Order();
	$order->set_date_created( $created_ts );
	if ( $confirmed_by ) {
		$order->update_meta_data( '_aod_confirmed_by', (int) $confirmed_by );
		$order->update_meta_data( '_aod_confirmed_at', wp_date( 'Y-m-d H:i:s', $created_ts + (int) $confirmed_after ) );
	}
	$order->set_status( $status );
	$order->save();
	return $order->get_id();
};

/**
 * Assigne une commande à un confirmateur (48.2).
 *
 * 🚨 DÉLIBÉRÉMENT SÉPARÉ DE `$aod_sc_make_order` — assignation et confirmation
 * sont deux notions ORTHOGONALES, et un décor qui les poserait ensemble rendrait
 * R5 incapable de les distinguer : « la carte affiche 3 » serait vrai que le code
 * lise `assigned` ou `rows`. Le décor ci-dessous les croise exprès (B est assigné
 * sans rien confirmer, A confirme 3 fois en n'étant assigné qu'une, une commande
 * confirmée par C est assignée à B — la réassignation après confirmation, qui est
 * précisément le cas d'où vient la division par zéro de D-4).
 */
$aod_sc_assign = function ( $id, $uid ) {
	$o = wc_get_order( $id );
	if ( $o instanceof WC_Order ) {
		$o->update_meta_data( '_aod_assigned_confirmer', (int) $uid );
		$o->save();
	}
};

/** Suppression RÉELLE — jamais `wp_delete_post()` en HPOS (cf. TEMPLATE.md §3). */
$aod_sc_del_order = function ( $id ) {
	$o = wc_get_order( $id );
	if ( $o instanceof WC_Order ) {
		$o->delete( true );
	}
};

$aod_sc_confA = $aod_sc_make_conf( 'aod_test_47_5_a' ); // Confirme.
$aod_sc_confB = $aod_sc_make_conf( 'aod_test_47_5_b', false ); // Ne confirme RIEN — comme le compte B de la porte.
$aod_sc_confC = $aod_sc_make_conf( 'aod_test_47_5_c' ); // L'« autre » : ce qui doit disparaître des cartes de A.

AOD_Test::ok( $aod_sc_confA && $aod_sc_confB && $aod_sc_confC, 'Fixtures : 3 confirmateurs porteurs de aod_cap_stats_confirm' );

$aod_sc_gerants = get_users( array( 'role__in' => array( 'administrator' ), 'number' => 1, 'fields' => 'ID' ) );
$aod_sc_gerant  = $aod_sc_gerants ? (int) $aod_sc_gerants[0] : 0;
AOD_Test::ok( $aod_sc_gerant > 0, 'Un gérant existe (identité de contre-épreuve)' );

// Fenêtre témoin : entièrement DANS LE PASSÉ, donc jamais confondue avec la
// période par défaut de l'écran (30 derniers jours).
$aod_sc_range = array(
	'from' => wp_date( 'Y-m-d', time() - 40 * DAY_IN_SECONDS ),
	'to'   => wp_date( 'Y-m-d', time() - 31 * DAY_IN_SECONDS ),
	'key'  => 'libre',
);
$aod_sc_prev    = AOD_Test::call( $aod_sc_home, 'previous_range', array( $aod_sc_range ) );
$aod_sc_filters = array(
	'onglet'       => 'confirmation',
	'produit'      => 0,
	'confirmateur' => 0,
	'periode'      => '',
	'date_from'    => $aod_sc_range['from'],
	'date_to'      => $aod_sc_range['to'],
);

$aod_sc_ts   = time() - 38 * DAY_IN_SECONDS; // Dans la fenêtre courante.
$aod_sc_tsp  = time() - 46 * DAY_IN_SECONDS; // Dans la fenêtre PRÉCÉDENTE.
$aod_sc_nes  = array();

// Fenêtre courante : A confirme 3 fois (24 h), C confirme 2 fois (48 h),
// 1 commande « confirmée sans traçabilité », 2 commandes ordinaires.
for ( $i = 0; $i < 3; $i++ ) {
	$aod_sc_nes[] = $aod_sc_make_order( $aod_sc_ts, 'completed', $aod_sc_confA, DAY_IN_SECONDS );
}
for ( $i = 0; $i < 2; $i++ ) {
	$aod_sc_nes[] = $aod_sc_make_order( $aod_sc_ts, 'completed', $aod_sc_confC, 2 * DAY_IN_SECONDS );
}
$aod_sc_nes[] = $aod_sc_make_order( $aod_sc_ts, 'completed' ); // Non attribuée.
$aod_sc_nes[] = $aod_sc_make_order( $aod_sc_ts, 'pending' );
$aod_sc_nes[] = $aod_sc_make_order( $aod_sc_ts, 'pending' );

// Fenêtre PRÉCÉDENTE, semée À L'ENVERS : A y est minoritaire (1) alors qu'il est
// majoritaire dans la courante (3). Un delta calculé « sa ligne maintenant vs le
// total d'avant » sort donc un nombre franchement différent — c'est ce qui rend
// R3 capable de distinguer les deux calculs.
$aod_sc_nes[] = $aod_sc_make_order( $aod_sc_tsp, 'completed', $aod_sc_confA, DAY_IN_SECONDS );
for ( $i = 0; $i < 4; $i++ ) {
	$aod_sc_nes[] = $aod_sc_make_order( $aod_sc_tsp, 'completed', $aod_sc_confC, DAY_IN_SECONDS );
}

AOD_Test::eq( 13, count( array_filter( $aod_sc_nes ) ), 'Décor semé : 8 commandes dans la fenêtre, 5 dans la précédente' );

// Assignations (48.2) — croisées avec les confirmations À DESSEIN. Fenêtre
// courante : indices 0-2 confirmés par A, 3-4 par C, 5 confirmée sans trace,
// 6-7 en attente.
//   · B est assigné 4 fois et n'a JAMAIS rien confirmé → c'est le témoin central :
//     avant la 48.2, cet écran lui montrait le total de la boutique.
//   · A confirme 3 fois mais n'est assigné qu'UNE → « assignées » ne peut pas être
//     lue comme un synonyme de « confirmées ».
//   · C n'est assigné à RIEN alors qu'il confirme 2 fois → le zéro est mesuré, pas
//     supposé.
//   · L'indice 3 est confirmé par C ET assigné à B : une réassignation APRÈS
//     confirmation, exactement le cas qui interdit de mettre ces deux chiffres en
//     fraction l'un de l'autre (D-4).
$aod_sc_assign( $aod_sc_nes[0], $aod_sc_confA );
$aod_sc_assign( $aod_sc_nes[3], $aod_sc_confB );
$aod_sc_assign( $aod_sc_nes[5], $aod_sc_confB );
$aod_sc_assign( $aod_sc_nes[6], $aod_sc_confB );
$aod_sc_assign( $aod_sc_nes[7], $aod_sc_confB );

/**
 * Découpe le HTML sur la ZONE DES CARTES (tout ce qui précède le tableau).
 *
 * 🚨 SANS ELLE, UNE ASSERTION EST VERTE SUR UN CODE BUGGÉ. Mesuré en écrivant ce
 * cliquet : « le délai moyen de A vaut 24,0 h » passait AVANT le correctif,
 * parce que « 24,0 h » figure aussi dans la colonne « Délai moyen » du tableau
 * « Par confirmateur » — lequel restreignait déjà correctement à sa ligne. La
 * carte, elle, affichait bien le délai de la boutique. Chercher une chaîne dans
 * TOUT le document, c'est accepter qu'un autre composant réponde à sa place.
 */
$aod_sc_cartes = function ( $html ) {
	$pos = strpos( $html, 'aod-cd-form-subtitle' );
	return false === $pos ? $html : substr( $html, 0, $pos );
};

/** Rend l'onglet Confirmation sous une identité donnée et rend le HTML. */
$aod_sc_rendu = function ( $uid ) use ( $aod_sc_home, $aod_sc_range, $aod_sc_filters, $aod_sc_reset ) {
	wp_set_current_user( $uid );
	$aod_sc_reset( $aod_sc_home );
	ob_start();
	AOD_Test::call( $aod_sc_home, 'tab_confirmation', array( $aod_sc_range, $aod_sc_filters ) );
	return (string) ob_get_clean();
};

try {
	// ── Attendus DÉRIVÉS de la donnée réelle (jamais écrits en dur) ────────────
	wp_set_current_user( $aod_sc_gerant );
	$aod_sc_reset( $aod_sc_home );
	$aod_sc_d = AOD_Test::call( $aod_sc_home, 'confirmation_data', array( $aod_sc_range, $aod_sc_filters ) );
	$aod_sc_p = AOD_Test::call( $aod_sc_home, 'confirmation_data', array( $aod_sc_prev, $aod_sc_filters ) );

	$aod_sc_vide  = array( 'n' => 0, 'delay_sum' => 0, 'delay_n' => 0 );
	$aod_sc_rowA  = isset( $aod_sc_d['rows'][ $aod_sc_confA ] ) ? $aod_sc_d['rows'][ $aod_sc_confA ] : $aod_sc_vide;
	$aod_sc_prowA = isset( $aod_sc_p['rows'][ $aod_sc_confA ] ) ? $aod_sc_p['rows'][ $aod_sc_confA ] : $aod_sc_vide;

	AOD_Test::eq( 0, count( AOD_Test::call( $aod_sc_home, 'user_scope' ) ), 'Périmètre produits du gérant vide (sinon le décor serait filtré hors des agrégats)' );

	printf(
		"    ⓘ décor mesuré — reçues %d · confirmées boutique %d · ligne de A %d · non attribuées %d · B 0\n",
		(int) $aod_sc_d['total'],
		(int) $aod_sc_d['confirmed'],
		(int) $aod_sc_rowA['n'],
		(int) $aod_sc_d['unassigned']
	);

	// 🎯 LE TÉMOIN QUI EMPÊCHE R2 D'ÊTRE VRAIE PAR ACCIDENT. Si la ligne de A
	// valait le total de la boutique, « A voit sa ligne » et « A voit la
	// boutique » seraient le MÊME vert.
	AOD_Test::ok(
		(int) $aod_sc_d['total'] > (int) $aod_sc_d['confirmed']
		&& (int) $aod_sc_d['confirmed'] > (int) $aod_sc_rowA['n']
		&& (int) $aod_sc_rowA['n'] > 0,
		'🔴 Témoin : reçues > confirmées boutique > ligne de A > 0 — les trois nombres sont DISTINCTS et non nuls'
	);
	AOD_Test::ok( (int) $aod_sc_d['unassigned'] > 0, 'Témoin : au moins une « non attribuée » (sinon AC10 serait vraie par absence de donnée)' );
	AOD_Test::ok( ! isset( $aod_sc_d['rows'][ $aod_sc_confB ] ), 'Témoin : B n’a AUCUNE ligne — son attendu est 0, pas le total de la boutique' );

	$aod_sc_rate_boutique = AOD_Test::call( $aod_sc_home, 'delivery_rate', array( $aod_sc_d['confirmed'], $aod_sc_d['total'] ) );
	$aod_sc_rate_A        = AOD_Test::call( $aod_sc_home, 'delivery_rate', array( $aod_sc_rowA['n'], $aod_sc_d['total'] ) );
	$aod_sc_rate_B        = AOD_Test::call( $aod_sc_home, 'delivery_rate', array( 0, $aod_sc_d['total'] ) );
	$aod_sc_delai_bout    = AOD_Test::call( $aod_sc_home, 'delay_fmt', array( $aod_sc_d['delay_sum'], $aod_sc_d['delay_n'] ) );
	$aod_sc_delai_A       = AOD_Test::call( $aod_sc_home, 'delay_fmt', array( $aod_sc_rowA['delay_sum'], $aod_sc_rowA['delay_n'] ) );

	AOD_Test::ok( $aod_sc_rate_boutique !== $aod_sc_rate_A && $aod_sc_rate_A !== $aod_sc_rate_B, 'Témoin : les 3 libellés de « Confirmées » sont distincts (boutique / A / B)' );
	AOD_Test::ok( $aod_sc_delai_bout !== $aod_sc_delai_A, 'Témoin : le délai moyen de A diffère de celui de la boutique (' . $aod_sc_delai_A . ' vs ' . $aod_sc_delai_bout . ')' );

	/* =====================================================================
	 * R1 — `confirmation_data()` reste GLOBALE : ancrage de non-régression.
	 * ===================================================================== */

	AOD_Test::group( 'Stats confirmateur 47.5 — R1 : la DONNÉE n’est pas scopée (D5)' );

	// 🚨 UNE PLAGE ÉLARGIE D'UN JOUR, ET CE N'EST PAS UN DÉTAIL. La clé de
	// `cached()` porte plage + produit + périmètre produits — JAMAIS l'identité,
	// ni l'assignation. Rejouer ici la plage déjà lue par le gérant rendrait le
	// TRANSIENT DU GÉRANT : R1 serait verte sans qu'aucune requête ne parte sous
	// cette identité, donc verte y compris si `confirmation_data()` s'était mise
	// à scoper par assignation. Mesuré en éprouvant le red-proof (M3). Un jour de
	// plus vers le passé : mêmes 8 commandes témoins (créées à J-38), clé neuve.
	$aod_sc_range_r1 = array(
		'from' => wp_date( 'Y-m-d', time() - 41 * DAY_IN_SECONDS ),
		'to'   => $aod_sc_range['to'],
		'key'  => 'libre',
	);
	wp_set_current_user( $aod_sc_confA );
	$aod_sc_reset( $aod_sc_home );
	$aod_sc_dA = AOD_Test::call( $aod_sc_home, 'confirmation_data', array( $aod_sc_range_r1, $aod_sc_filters ) );

	AOD_Test::ok(
		isset( $aod_sc_dA['rows'][ $aod_sc_confC ] ) && (int) $aod_sc_dA['rows'][ $aod_sc_confC ]['n'] > 0,
		'🔴 Appelée EN CONFIRMATEUR, confirmation_data() rend toujours la ligne d’un AUTRE confirmateur (elle restreint au rendu, jamais à la requête)'
	);
	AOD_Test::eq( (int) $aod_sc_d['confirmed'], (int) $aod_sc_dA['confirmed'], 'Le total « confirmées » vu par un confirmateur est celui de la boutique (la donnée ne dépend pas du lecteur)' );
	AOD_Test::eq( (int) $aod_sc_d['total'], (int) $aod_sc_dA['total'], 'Idem pour « reçues »' );

	/* =====================================================================
	 * R2 — LA RÈGLE FONCTIONNELLE : le RENDU sous trois identités.
	 * ===================================================================== */

	AOD_Test::group( 'Stats confirmateur 47.5 — R2 : le RENDU sert la bonne ligne' );

	$aod_sc_html_g = $aod_sc_rendu( $aod_sc_gerant );
	$aod_sc_html_a = $aod_sc_rendu( $aod_sc_confA );
	$aod_sc_html_b = $aod_sc_rendu( $aod_sc_confB );

	AOD_Test::ok( '' !== $aod_sc_html_g && '' !== $aod_sc_html_a && '' !== $aod_sc_html_b, 'Témoin : les trois rendus sont non vides (' . strlen( $aod_sc_html_g ) . ' / ' . strlen( $aod_sc_html_a ) . ' / ' . strlen( $aod_sc_html_b ) . ' octets)' );

	$aod_sc_kpi_g = $aod_sc_cartes( $aod_sc_html_g );
	$aod_sc_kpi_a = $aod_sc_cartes( $aod_sc_html_a );
	$aod_sc_kpi_b = $aod_sc_cartes( $aod_sc_html_b );
	AOD_Test::ok(
		strlen( $aod_sc_kpi_a ) > 0 && strlen( $aod_sc_kpi_a ) < strlen( $aod_sc_html_a ),
		'Témoin : la zone des CARTES est bien isolée du tableau (' . strlen( $aod_sc_kpi_a ) . ' octets sur ' . strlen( $aod_sc_html_a ) . ')'
	);

	AOD_Test::ok( false !== strpos( $aod_sc_kpi_g, $aod_sc_rate_boutique ), 'GÉRANT : la carte « Confirmées » porte le nombre de la BOUTIQUE (' . $aod_sc_rate_boutique . ')' );
	AOD_Test::ok( false !== strpos( $aod_sc_kpi_g, $aod_sc_delai_bout ), 'GÉRANT : le délai moyen est celui de la boutique (' . $aod_sc_delai_bout . ')' );

	AOD_Test::ok( false !== strpos( $aod_sc_kpi_a, $aod_sc_rate_A ), '🎯 CONFIRMATEUR A : la carte « Confirmées » porte SA ligne (' . $aod_sc_rate_A . ')' );
	AOD_Test::ok( false === strpos( $aod_sc_html_a, $aod_sc_rate_boutique ), '🔴 CONFIRMATEUR A : le nombre de la BOUTIQUE (' . $aod_sc_rate_boutique . ') n’apparaît NULLE PART dans son rendu' );
	AOD_Test::ok( false !== strpos( $aod_sc_kpi_a, $aod_sc_delai_A ), '🎯 CONFIRMATEUR A : la CARTE de délai porte SES confirmations (' . $aod_sc_delai_A . ')' );
	AOD_Test::ok( false === strpos( $aod_sc_html_a, $aod_sc_delai_bout ), '🔴 CONFIRMATEUR A : le délai de la boutique (' . $aod_sc_delai_bout . ') n’apparaît pas' );

	AOD_Test::ok( false !== strpos( $aod_sc_kpi_b, $aod_sc_rate_B ), '🎯 CONFIRMATEUR B (n’a rien confirmé) : la carte affiche ' . $aod_sc_rate_B );
	AOD_Test::ok( false === strpos( $aod_sc_html_b, $aod_sc_rate_boutique ), '🔴 CONFIRMATEUR B : il ne lit PAS le nombre de la boutique — un « 0 » est sa vraie performance' );

	// « Commandes reçues » reste le total de la boutique pour TOUT LE MONDE (D-3) :
	// un « reçues par moi » serait un numérateur figé sur un dénominateur mobile.
	$aod_sc_recues = number_format_i18n( $aod_sc_d['total'] );
	AOD_Test::ok( false !== strpos( $aod_sc_kpi_a, '>' . $aod_sc_recues . '<' ), 'D-3 : « Commandes reçues » reste le total de la boutique (' . $aod_sc_recues . ') même en confirmateur' );

	// La note qui évite le contresens (AC5) : présente pour lui, absente pour le gérant.
	$aod_sc_note = 'aod-cd-note-scope-conf';
	AOD_Test::ok( false !== strpos( $aod_sc_html_a, $aod_sc_note ), 'AC5 : la note « le pourcentage se rapporte à la boutique » est rendue au confirmateur' );
	AOD_Test::ok( false === strpos( $aod_sc_html_g, $aod_sc_note ), 'AC5 : cette note n’est PAS rendue au gérant' );

	/* =====================================================================
	 * R3 — La période précédente suit le MÊME périmètre.
	 * ===================================================================== */

	AOD_Test::group( 'Stats confirmateur 47.5 — R3 : le delta compare deux fois le même périmètre' );

	wp_set_current_user( $aod_sc_gerant );
	$aod_sc_reset( $aod_sc_home );
	$aod_sc_taux_A   = $aod_sc_d['total'] > 0 ? ( $aod_sc_rowA['n'] / $aod_sc_d['total'] ) * 100 : 0.0;
	$aod_sc_taux_pA  = $aod_sc_p['total'] > 0 ? ( $aod_sc_prowA['n'] / $aod_sc_p['total'] ) * 100 : 0.0;
	$aod_sc_taux_pG  = $aod_sc_p['total'] > 0 ? ( $aod_sc_p['confirmed'] / $aod_sc_p['total'] ) * 100 : 0.0;
	$aod_sc_delta_ok = AOD_Test::call( $aod_sc_home, 'kpi_delta', array( $aod_sc_taux_A, $aod_sc_taux_pA, true, true ) );
	$aod_sc_delta_ko = AOD_Test::call( $aod_sc_home, 'kpi_delta', array( $aod_sc_taux_A, $aod_sc_taux_pG, true, true ) );

	AOD_Test::ok( $aod_sc_delta_ok !== $aod_sc_delta_ko, 'Témoin : les deux deltas concurrents sont distinguables (sa ligne avant vs le total avant)' );
	AOD_Test::ok( false !== strpos( $aod_sc_kpi_a, $aod_sc_delta_ok ), '🎯 Le delta de A compare SA ligne d’une période à l’autre' );
	AOD_Test::ok( false === strpos( $aod_sc_html_a, $aod_sc_delta_ko ), '🔴 Le delta de A ne compare JAMAIS sa ligne actuelle au total global d’avant' );

	// Le délai moyen a le même contrat de comparaison.
	$aod_sc_ddelai_ok = ( $aod_sc_rowA['delay_n'] && $aod_sc_prowA['delay_n'] )
		? AOD_Test::call( $aod_sc_home, 'kpi_delta', array( $aod_sc_rowA['delay_sum'] / $aod_sc_rowA['delay_n'], $aod_sc_prowA['delay_sum'] / $aod_sc_prowA['delay_n'], false ) )
		: '';
	AOD_Test::ok(
		'' === $aod_sc_ddelai_ok || false !== strpos( $aod_sc_kpi_a, $aod_sc_ddelai_ok ),
		'Le delta du délai moyen compare lui aussi deux fois SA ligne'
	);

	/* =====================================================================
	 * R4 — Ce qui NE DOIT PAS bouger.
	 * ===================================================================== */

	AOD_Test::group( 'Stats confirmateur 47.5 — R4 : « Part », « Non attribuées », périmètre du tableau' );

	// « Part » = ligne / TOTAL GLOBAL, y compris pour un confirmateur. C'est le
	// seul chiffre d'équipe qui lui reste, et c'est intentionnel (D5).
	$aod_sc_part_ok = number_format_i18n( $aod_sc_rowA['n'] / $aod_sc_d['confirmed'] * 100, 1 ) . ' %';
	$aod_sc_part_ko = number_format_i18n( 100, 1 ) . ' %'; // Ce que donnerait « ligne / ligne ».
	AOD_Test::ok( $aod_sc_part_ok !== $aod_sc_part_ko, 'Témoin : la « Part » correcte de A n’est pas 100 % (donc les deux calculs sont distinguables)' );
	AOD_Test::ok( false !== strpos( $aod_sc_html_a, $aod_sc_part_ok ), '🎯 « Part » divise toujours par le total global de la boutique (' . $aod_sc_part_ok . ')' );
	AOD_Test::ok( false === strpos( $aod_sc_html_a, '>' . $aod_sc_part_ko . '<' ), '🔴 « Part » ne divise PAS par la ligne du confirmateur' );

	$aod_sc_lbl_na   = __( 'Non attribuées', 'aod-client-dashboard' );
	$aod_sc_lbl_card = __( 'Confirmées non attribuées', 'aod-client-dashboard' );
	AOD_Test::ok( false !== strpos( $aod_sc_html_g, $aod_sc_lbl_na ), 'GÉRANT : la ligne « Non attribuées » est rendue (le décor en contient)' );
	AOD_Test::ok( false === strpos( $aod_sc_html_a, $aod_sc_lbl_na ), '🔴 CONFIRMATEUR : la ligne « Non attribuées » reste absente' );
	AOD_Test::ok( false !== strpos( $aod_sc_html_g, $aod_sc_lbl_card ), 'GÉRANT : la carte « Confirmées non attribuées » est rendue' );
	AOD_Test::ok( false === strpos( $aod_sc_html_a, $aod_sc_lbl_card ), 'CONFIRMATEUR : cette carte reste absente' );

	// Le tableau « Par confirmateur » ne montre que SA ligne — jamais celle de C.
	$aod_sc_nomC = get_userdata( $aod_sc_confC ) ? get_userdata( $aod_sc_confC )->display_name : '';
	$aod_sc_nomA = get_userdata( $aod_sc_confA ) ? get_userdata( $aod_sc_confA )->display_name : '';
	AOD_Test::ok( '' !== $aod_sc_nomA && '' !== $aod_sc_nomC, 'Témoin : les deux noms d’affichage existent' );
	AOD_Test::ok( false !== strpos( $aod_sc_html_a, $aod_sc_nomA ), 'CONFIRMATEUR A : sa propre ligne est rendue' );
	AOD_Test::ok( false === strpos( $aod_sc_html_a, $aod_sc_nomC ), '🔴 CONFIRMATEUR A : la ligne d’un autre confirmateur reste absente' );
	AOD_Test::ok( false !== strpos( $aod_sc_html_g, $aod_sc_nomC ), 'GÉRANT : il voit bien toutes les lignes (contre-épreuve)' );

	// Le filtre d'écran du gérant, lui, ne bouge pas d'un iota (AC7).
	$aod_sc_filtre_A = array_merge( $aod_sc_filters, array( 'confirmateur' => $aod_sc_confA ) );
	wp_set_current_user( $aod_sc_gerant );
	$aod_sc_reset( $aod_sc_home );
	ob_start();
	AOD_Test::call( $aod_sc_home, 'tab_confirmation', array( $aod_sc_range, $aod_sc_filtre_A ) );
	$aod_sc_html_gf = (string) ob_get_clean();
	AOD_Test::ok( false !== strpos( $aod_sc_html_gf, $aod_sc_rate_A ), 'AC7 : gérant + ?confirmateur=A → les cartes portent la ligne de A' );
	AOD_Test::ok( false === strpos( $aod_sc_html_gf, $aod_sc_note ), 'AC7 : la note du confirmateur n’apparaît pas pour un gérant qui filtre' );

	/* =====================================================================
	 * R5 — La carte « Commandes assignées » et le libellé de périmètre (Phase 48)
	 *
	 * 🚨 CE QUE CETTE RÈGLE GARDE, ET QUI N'EST PAS « une carte de plus ».
	 * Avant la Phase 48, un confirmateur à qui 12 commandes étaient assignées
	 * lisait « Commandes reçues : 33 » — un chiffre EXACT, sous un libellé muet
	 * sur son périmètre, dans un écran où tout le reste de son /gestion est
	 * restreint à son assignation. Le défaut n'était pas un calcul faux : c'était
	 * un nombre juste qu'on ne pouvait pas lire juste.
	 * ===================================================================== */

	AOD_Test::group( 'Stats confirmateur 48 — R5 : la carte des assignées, et le périmètre dit' );

	$aod_sc_asg    = isset( $aod_sc_d['assigned'] ) ? $aod_sc_d['assigned'] : array();
	$aod_sc_asgA   = isset( $aod_sc_asg[ $aod_sc_confA ] ) ? (int) $aod_sc_asg[ $aod_sc_confA ] : 0;
	$aod_sc_asgB   = isset( $aod_sc_asg[ $aod_sc_confB ] ) ? (int) $aod_sc_asg[ $aod_sc_confB ] : 0;
	$aod_sc_asgC   = isset( $aod_sc_asg[ $aod_sc_confC ] ) ? (int) $aod_sc_asg[ $aod_sc_confC ] : 0;
	$aod_sc_lbl_as = __( 'Commandes assignées', 'aod-client-dashboard' );

	printf(
		"    ⓘ assignations mesurées — A %d (confirme %d) · B %d (confirme 0) · C %d (confirme %d)\n",
		$aod_sc_asgA,
		(int) $aod_sc_rowA['n'],
		$aod_sc_asgB,
		$aod_sc_asgC,
		isset( $aod_sc_d['rows'][ $aod_sc_confC ] ) ? (int) $aod_sc_d['rows'][ $aod_sc_confC ]['n'] : 0
	);

	// TÉMOINS DE DISCRIMINANCE. Sans eux, « la carte de B affiche 4 » pourrait
	// être vraie d'un code qui lit `rows`, `total`, ou n'importe quoi d'autre.
	AOD_Test::eq( 4, $aod_sc_asgB, 'TÉMOIN : B est assigné 4 fois (et n’a rien confirmé — les deux notions sont bien croisées)' );
	AOD_Test::eq( 1, $aod_sc_asgA, 'TÉMOIN : A est assigné 1 fois alors qu’il confirme ' . (int) $aod_sc_rowA['n'] . ' — « assignées » n’est pas « confirmées »' );
	AOD_Test::ok(
		$aod_sc_asgB !== (int) $aod_sc_d['total'] && $aod_sc_asgB !== (int) $aod_sc_d['confirmed'] && $aod_sc_asgB !== (int) $aod_sc_d['unassigned'],
		'TÉMOIN : ' . $aod_sc_asgB . ' n’est aucun autre nombre de l’écran (total ' . (int) $aod_sc_d['total'] . ', confirmées ' . (int) $aod_sc_d['confirmed'] . ', non attribuées ' . (int) $aod_sc_d['unassigned'] . ')'
	);
	AOD_Test::ok( ! array_key_exists( 0, $aod_sc_asg ), '🔴 Aucune entrée `0` : une commande NON assignée n’est pas « assignée à personne » (invariant Phase 42)' );

	// La donnée reste INDÉPENDANTE DU LECTEUR — jumeau de R1 (D5) pour `assigned`.
	wp_set_current_user( $aod_sc_confB );
	$aod_sc_reset( $aod_sc_home );
	$aod_sc_dB = AOD_Test::call( $aod_sc_home, 'confirmation_data', array( $aod_sc_range, $aod_sc_filters ) );
	AOD_Test::eq(
		$aod_sc_asg,
		isset( $aod_sc_dB['assigned'] ) ? $aod_sc_dB['assigned'] : array(),
		'🔴 La ventilation `assigned` lue EN CONFIRMATEUR est la même qu’en gérant (elle est en cache : la clé n’intègre pas le lecteur, la visibilité se décide au rendu)'
	);

	$aod_sc_html_b2 = $aod_sc_rendu( $aod_sc_confB );
	$aod_sc_kpi_b2  = $aod_sc_cartes( $aod_sc_html_b2 );
	$aod_sc_kpi_c   = $aod_sc_cartes( $aod_sc_rendu( $aod_sc_confC ) );

	AOD_Test::ok( false !== strpos( $aod_sc_kpi_b2, $aod_sc_lbl_as ), 'CONFIRMATEUR B : la carte « Commandes assignées » est rendue' );
	AOD_Test::ok(
		false !== strpos( $aod_sc_kpi_b2, '>' . number_format_i18n( $aod_sc_asgB ) . '<' ),
		'🎯 CONFIRMATEUR B : elle porte SES ' . $aod_sc_asgB . ' commandes assignées — pas le total de la boutique'
	);
	AOD_Test::ok(
		false === strpos( $aod_sc_kpi_c, '>' . number_format_i18n( $aod_sc_asgB ) . '<' ),
		'🔴 CONFIRMATEUR C : le ' . $aod_sc_asgB . ' de B n’apparaît PAS chez lui (il n’est assigné à rien)'
	);
	AOD_Test::ok(
		false !== strpos( $aod_sc_kpi_c, '>' . number_format_i18n( 0 ) . '<' ),
		'🎯 CONFIRMATEUR C : assigné à rien, il lit 0 — jamais le total de la boutique'
	);

	// Non-régression n°1 de la phase : le gérant SANS filtre ne voit rien de neuf.
	AOD_Test::ok( false === strpos( $aod_sc_cartes( $aod_sc_html_g ), $aod_sc_lbl_as ), '🔴 GÉRANT sans filtre : la carte reste ABSENTE (son rendu ne bouge pas)' );
	// …mais le gérant qui filtre B la voit (D-2 : la carte suit $row_uid).
	wp_set_current_user( $aod_sc_gerant );
	$aod_sc_reset( $aod_sc_home );
	ob_start();
	AOD_Test::call( $aod_sc_home, 'tab_confirmation', array( $aod_sc_range, array_merge( $aod_sc_filters, array( 'confirmateur' => $aod_sc_confB ) ) ) );
	$aod_sc_kpi_gb = $aod_sc_cartes( (string) ob_get_clean() );
	AOD_Test::ok( false !== strpos( $aod_sc_kpi_gb, $aod_sc_lbl_as ), 'D-2 : gérant + ?confirmateur=B → la carte suit `$row_uid`, elle est rendue' );
	AOD_Test::ok( false !== strpos( $aod_sc_kpi_gb, '>' . number_format_i18n( $aod_sc_asgB ) . '<' ), 'D-2 : et elle porte les ' . $aod_sc_asgB . ' commandes de B' );

	// 48.1 — le libellé nomme la boutique pour le seul lecteur qui peut s'y tromper.
	$aod_sc_lbl_recues_g = __( 'Commandes reçues', 'aod-client-dashboard' );
	$aod_sc_lbl_recues_c = __( 'Commandes reçues (boutique)', 'aod-client-dashboard' );
	AOD_Test::ok( $aod_sc_lbl_recues_g !== $aod_sc_lbl_recues_c, 'TÉMOIN : les deux libellés sont distincts (sinon les deux assertions suivantes seraient vraies par accident)' );
	AOD_Test::ok(
		false !== strpos( $aod_sc_kpi_b2, '>' . $aod_sc_lbl_recues_c . '<' ) && false === strpos( $aod_sc_kpi_b2, '>' . $aod_sc_lbl_recues_g . '<' ),
		'🎯 CONFIRMATEUR : « Commandes reçues » dit BOUTIQUE — et jamais les deux libellés à la fois'
	);
	AOD_Test::ok(
		false !== strpos( $aod_sc_cartes( $aod_sc_html_g ), '>' . $aod_sc_lbl_recues_g . '<' ) && false === strpos( $aod_sc_html_g, $aod_sc_lbl_recues_c ),
		'🔴 GÉRANT : il garde EXACTEMENT sa chaîne (non-régression n°1)'
	);

	// 🚨 48.4 — L'ORDRE FAIT PARTIE DU MESSAGE, et il se garde comme le reste.
	// Livrée en 4ᵉ position, cette carte était exacte et INUTILE : le premier
	// nombre lu par un confirmateur restait celui de la boutique, sous un
	// « Bonjour <prénom> ». Retour de l'owner : « rien n'est corrigé ». Sans ce
	// cliquet, un futur `$cards[] = …` la renverrait en queue sans qu'aucune
	// assertion ne bouge — elle serait toujours « rendue », toujours juste, et de
	// nouveau sans effet.
	$aod_sc_pos_as  = strpos( $aod_sc_kpi_b2, $aod_sc_lbl_as );
	$aod_sc_pos_rec = strpos( $aod_sc_kpi_b2, $aod_sc_lbl_recues_c );
	AOD_Test::ok(
		false !== $aod_sc_pos_as && false !== $aod_sc_pos_rec,
		'TÉMOIN : les deux cartes sont rendues (sinon la comparaison de position ne dirait rien)'
	);
	AOD_Test::ok(
		$aod_sc_pos_as < $aod_sc_pos_rec,
		'🎯 48.4 : « Commandes assignées » précède « Commandes reçues (boutique) » — ajouter la bonne information ne corrige rien tant qu\'elle arrive après celle qui trompe'
	);

	// 48.1 — la note de périmètre arrive AVANT le tableau, là où naît le contresens.
	$aod_sc_pos_note = strpos( $aod_sc_html_b2, $aod_sc_note );
	$aod_sc_pos_tab  = strpos( $aod_sc_html_b2, 'aod-cd-form-subtitle' );
	AOD_Test::ok( false !== $aod_sc_pos_note && false !== $aod_sc_pos_tab, 'TÉMOIN : la note et le tableau sont tous deux rendus (sinon la comparaison de position serait vide de sens)' );
	AOD_Test::ok(
		$aod_sc_pos_note < $aod_sc_pos_tab,
		'🎯 La note de périmètre est rendue AVANT le tableau — une note exacte lue après s’être trompé n’a rien évité'
	);

	/* =====================================================================
	 * R6 — Une ASSIGNATION invalide le cache des agrégats (Phase 48.5)
	 *
	 * 🚨 CE QUE CETTE RÈGLE GARDE. La carte de R5 sort de `confirmation_data()`,
	 * qui passe par `cached()` (transient, TTL 5 min). Les quatre hooks
	 * d'invalidation historiques ne voyaient que la création, le changement de
	 * statut et les suppressions — or assigner une commande n'écrit qu'une MÉTA.
	 * Mesuré sur le clone au moment de la 48.5 : 15 commandes assignées en base,
	 * 14 affichées. Le gérant assigne, le confirmateur regarde, et le seul chiffre
	 * de l'écran qui parle DE LUI lui donne tort, pendant cinq minutes, en silence.
	 *
	 * ⚠️ Ce cliquet n'interroge PAS `has_action()` : décrire un branchement, c'est
	 * décrire l'état au lieu de le provoquer, et ça reste vert si le hook cesse
	 * d'être émis sur ce chemin. Il CHAUFFE le cache, assigne par le code de
	 * production (`apply_assignment()`, extraite du handler AJAX précisément pour
	 * être appelable ici), puis relit. Le témoin de version prouve au passage que
	 * le vert ne vient pas d'un cache absent.
	 * ===================================================================== */

	AOD_Test::group( 'Stats confirmateur 48.5 — R6 : assigner invalide le cache' );

	// Un témoin NON assigné dans la fenêtre — cherché, jamais codé en dur : un
	// indice figé se décorrèle du décor à la première retouche du semis.
	$aod_sc_libre = 0;
	foreach ( array_slice( $aod_sc_nes, 0, 8 ) as $aod_sc_cand ) {
		$aod_sc_o = wc_get_order( $aod_sc_cand );
		if ( $aod_sc_o instanceof WC_Order && '' === (string) $aod_sc_o->get_meta( '_aod_assigned_confirmer' ) ) {
			$aod_sc_libre = (int) $aod_sc_cand;
			break;
		}
	}
	AOD_Test::ok( $aod_sc_libre > 0, 'TÉMOIN : une commande de la fenêtre est encore NON assignée (sinon il n’y a rien à faire varier)' );

	// 🚨 On assigne à A, pas à B. `apply_assignment()` valide sa cible en confiance
	// zéro (`AOD_CD_Roles::valid_confirmer`) et REFUSE un compte dépourvu de
	// `aod_cap_confirm_orders` — ce qui est le bon comportement : on n'assigne pas
	// une commande à qui n'a pas le droit de la confirmer. Or B est privé de cette
	// capability EXPRÈS (il ne confirme rien, c'est son rôle dans R5). Passer par
	// lui mesurerait donc un refus, pas une invalidation. Ce que R6 garde ne tient
	// pas à l'identité de l'assigné : c'est l'écriture de méta qui compte.
	$aod_sc_orders = AOD_CD_Section_Orders::instance();
	$aod_sc_lire   = function () use ( $aod_sc_home, $aod_sc_range, $aod_sc_filters, $aod_sc_reset, $aod_sc_confA ) {
		$aod_sc_reset( $aod_sc_home );
		$d = AOD_Test::call( $aod_sc_home, 'confirmation_data', array( $aod_sc_range, $aod_sc_filters ) );
		return isset( $d['assigned'][ $aod_sc_confA ] ) ? (int) $d['assigned'][ $aod_sc_confA ] : 0;
	};

	if ( $aod_sc_libre ) {
		// 1. Cache CHAUD : c'est cette valeur-là qui doit cesser d'être servie.
		$aod_sc_av    = $aod_sc_lire();
		$aod_sc_ver_a = AOD_CD_Section_Home::cache_version_for( $aod_sc_range['from'], $aod_sc_range['to'] );
		AOD_Test::eq( $aod_sc_asgA, $aod_sc_av, 'TÉMOIN : le cache est chaud sur les ' . $aod_sc_asgA . ' assignation(s) de A' );

		// 2. Assignation PAR LE CHEMIN DE PRODUCTION.
		$aod_sc_res = AOD_Test::call( $aod_sc_orders, 'apply_assignment', array( wc_get_order( $aod_sc_libre ), $aod_sc_confA ) );
		AOD_Test::ok( ! empty( $aod_sc_res['ok'] ), 'TÉMOIN : `apply_assignment()` a bien écrit (sinon la suite mesurerait un non-événement)' );

		$aod_sc_ver_b = AOD_CD_Section_Home::cache_version_for( $aod_sc_range['from'], $aod_sc_range['to'] );
		AOD_Test::ok( $aod_sc_ver_a !== $aod_sc_ver_b, '🎯 48.5 : l’empreinte de cache du mois CHANGE après une assignation — une méta est une écriture de commande' );

		AOD_Test::eq( $aod_sc_av + 1, $aod_sc_lire(), '🎯 48.5 : la carte suit IMMÉDIATEMENT l’assignation (' . $aod_sc_av . ' → ' . ( $aod_sc_av + 1 ) . '), sans attendre les 5 min du transient' );

		// 3. Le jumeau : la DÉ-assignation passe par la même méthode, branche
		//    « suppression de méta ». Sans ce contrôle, retirer une commande à un
		//    confirmateur pourrait rester invisible alors que l'ajout se voit.
		//
		// ⚠️ L'ASSERTION DE VALEUR NE SUFFIT PAS ICI, et le red-proof l'a montré :
		// hook retiré, la chaîne casse dès l'étape 2, le cache reste figé sur
		// `$aod_sc_av`… qui est précisément l'attendu du retour. Elle restait donc
		// VERTE sur un cache mort — vraie par coïncidence de nombres. L'empreinte,
		// elle, ne peut pas coïncider : elle doit changer à CHAQUE écriture.
		$aod_sc_res0 = AOD_Test::call( $aod_sc_orders, 'apply_assignment', array( wc_get_order( $aod_sc_libre ), 0 ) );
		AOD_Test::ok( ! empty( $aod_sc_res0['ok'] ), 'TÉMOIN : la dé-assignation a bien été appliquée' );
		$aod_sc_ver_c = AOD_CD_Section_Home::cache_version_for( $aod_sc_range['from'], $aod_sc_range['to'] );
		AOD_Test::ok( $aod_sc_ver_b !== $aod_sc_ver_c, '🎯 48.5 : la dé-assignation change l’empreinte à son tour — le jumeau tient SEUL, sans dépendre du succès de l’ajout' );
		AOD_Test::eq( $aod_sc_av, $aod_sc_lire(), '🎯 48.5 : et le chiffre redescend aussitôt (' . ( $aod_sc_av + 1 ) . ' → ' . $aod_sc_av . ')' );
	}
} finally {
	foreach ( $aod_sc_nes as $aod_sc_id ) {
		$aod_sc_del_order( $aod_sc_id );
	}
	wp_set_current_user( $aod_sc_user_avant );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array( $aod_sc_confA, $aod_sc_confB, $aod_sc_confC ) as $aod_sc_uid ) {
		if ( $aod_sc_uid ) {
			wp_delete_user( $aod_sc_uid );
		}
	}
}

AOD_Test::group( 'Stats confirmateur 47.5 — nettoyage' );

AOD_Test::ok( ! get_user_by( 'login', 'aod_test_47_5_a' ), 'Les confirmateurs témoins sont supprimés' );
AOD_Test::ok( ! wc_get_order( $aod_sc_nes[0] ), 'Les commandes témoins sont supprimées (contrôle sur la première)' );
