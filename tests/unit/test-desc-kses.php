<?php
/**
 * Contrat « barre d'outils ⇄ wp_kses_post » de la description produit (8.1).
 *
 * L'éditeur riche de /gestion (TinyMCE) n'a AUCUNE logique serveur à lui : il
 * s'appuie entièrement sur le nettoyage déjà en place — `wp_kses_post()` dans
 * `ajax_save_product()`. Le contrat implicite est donc : CHAQUE bouton de la
 * barre produit du HTML que kses conserve. Si quelqu'un durcit le nettoyage
 * (ou élargit la barre) plus tard, rien ne le signalerait — sauf ces tests.
 *
 * Trois choses sont prouvées :
 *   1. la grammaire que TinyMCE 4 émet réellement (strong/em — pas b/i —, h2,
 *      h3, ul/ol/li, style="text-align: …", a href) SURVIT à wp_kses_post ;
 *      ⚠️ kses NORMALISE le style : « text-align: center; » ressort SANS le
 *      point-virgule final — les assertions visent la forme normalisée, pas
 *      l'entrée à l'octet (sinon : faux rouge sur du code juste) ;
 *   2. ce que la barre ne produit pas mais qu'un POST forgé peut envoyer est
 *      neutralisé : <script>, on*, <iframe> (la vidéo 8.3 passera par un lien,
 *      jamais un iframe stocké), javascript: en href ;
 *   3. les tests DISCRIMINENT (leçon 7.3/7.4/7.5 : un test qui passerait aussi
 *      sur le mauvais code ne prouve rien) : la même grammaire passée au
 *      nettoyage « dur » (sanitize_textarea_field, celui qui aurait appauvri
 *      l'arabe en 7.2) ressort MUTILÉE — c'est la preuve que nos assertions
 *      tomberaient si quelqu'un remplaçait kses par lui.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Description riche — la grammaire de la barre survit à wp_kses_post' );

// Chaque entrée : [ HTML tel que TinyMCE 4 l'émet, sortie attendue de kses, bouton prouvé ].
$grammar = array(
	array( '<p><strong>gras</strong></p>', '<p><strong>gras</strong></p>', 'gras (strong, pas b)' ),
	array( '<p><em>italique</em></p>', '<p><em>italique</em></p>', 'italique (em, pas i)' ),
	array( '<h2>titre</h2>', '<h2>titre</h2>', 'titre (h2)' ),
	array( '<h3>sous-titre</h3>', '<h3>sous-titre</h3>', 'sous-titre (h3)' ),
	array( '<ul><li>puce</li></ul>', '<ul><li>puce</li></ul>', 'liste à puces' ),
	array( '<ol><li>un</li></ol>', '<ol><li>un</li></ol>', 'liste numérotée' ),
	// kses réécrit le style via safecss_filter_attr : le « ; » final tombe.
	array( '<p style="text-align: center;">centré</p>', '<p style="text-align: center">centré</p>', 'alignement (style normalisé, sans ;)' ),
	array( '<p><a href="https://example.com/page">lien</a></p>', '<p><a href="https://example.com/page">lien</a></p>', 'lien' ),
	// Image dans la description (8.2) : le markup MINIMAL stocké par l'éditeur
	// (`wp-image-{id}` porte le srcset au RENDU, pas en base). Survit à l'octet.
	array(
		'<img class="wp-image-42" src="https://x/a.jpg" alt="" width="800" height="600" />',
		'<img class="wp-image-42" src="https://x/a.jpg" alt="" width="800" height="600" />',
		'image insérée (markup minimal wp-image-{id})',
	),
);

foreach ( $grammar as $case ) {
	list( $in, $expected, $label ) = $case;
	AOD_Test::ok(
		wp_kses_post( $in ) === $expected,
		"Bouton « {$label} » : le HTML survit tel qu'attendu",
		wp_kses_post( $in )
	);
}

AOD_Test::group( 'Description riche — un POST forgé est neutralisé (la barre n\'est pas la frontière, kses l\'est)' );

$out = wp_kses_post( '<p>ok</p><script>alert(1)</script>' );
AOD_Test::ok( false === strpos( $out, '<script' ), 'Un <script> injecté ne survit pas', $out );

$out = wp_kses_post( '<p onclick="steal()">x</p>' );
AOD_Test::ok( false === strpos( $out, 'onclick' ), 'Un gestionnaire on* est retiré', $out );

$out = wp_kses_post( '<iframe src="https://youtube.com/embed/x"></iframe>' );
AOD_Test::ok( false === strpos( $out, '<iframe' ), 'Un <iframe> ne survit pas (la vidéo 8.3 passera par un lien, jamais un iframe stocké)', $out );

$out = wp_kses_post( '<a href="javascript:alert(1)">l</a>' );
AOD_Test::ok( false === strpos( $out, 'javascript:' ), 'Un href javascript: est désamorcé', $out );

$out = wp_kses_post( '<img src="javascript:alert(1)" alt="" />' );
AOD_Test::ok( false === strpos( $out, 'javascript:' ), 'Un <img src="javascript:…"> est désamorcé', $out );

AOD_Test::group( 'Description vidéo — les deux vecteurs kses de la 8.3 (YouTube lien vs <video> fichier)' );

// YOUTUBE : l'iframe est morte (ci-dessus) → on stocke un LIEN marqué. kses le
// conserve, classe ET data-yt compris : c'est ce qui permet au rendu de le
// reconnaître et de le convertir en façade (test-desc-video.php).
$marker = '<a class="aod-desc-video" href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">Voir</a>';
AOD_Test::ok( wp_kses_post( $marker ) === $marker, 'Le lien vidéo marqué (class aod-desc-video) survit à kses à l\'octet', wp_kses_post( $marker ) );

// FICHIER : kses CONSERVE <video src> — mais SUPPRIME <source> (le child). D'où la
// décision D7 : on stocke le `src` DIRECT sur <video>, jamais un <source> enfant.
$vsrc = '<video class="aod-desc-video-file" src="https://x/v.mp4" controls preload="none" playsinline></video>';
AOD_Test::ok( wp_kses_post( $vsrc ) === $vsrc, 'Un <video src> (fichier local) survit à kses à l\'octet', wp_kses_post( $vsrc ) );

$out = wp_kses_post( '<video controls><source src="https://x/v.mp4" type="video/mp4"></video>' );
AOD_Test::ok( false === strpos( $out, '<source' ), 'kses STRIPPE <source> → on met le src DIRECT sur <video> (D7), jamais un <source> enfant', $out );

$out = wp_kses_post( '<video src="javascript:alert(1)" controls></video>' );
AOD_Test::ok( false === strpos( $out, 'javascript:' ), 'Un <video src="javascript:…"> est désamorcé', $out );

AOD_Test::group( 'Description riche — l\'enrichissement d\'image vit AU RENDU, pas en base (8.2)' );

// On stocke le markup MINIMAL. `srcset`/`sizes`/`decoding` sont STRIPPÉS par kses :
// les stocker serait les perdre en silence. C'est voulu — `wp_filter_content_tags`
// (the_content, prio 12) les RÉGÉNÈRE au rendu depuis la classe `wp-image-{id}`.
// Ce test verrouille la décision : si un futur WP les acceptait, il rougirait et
// on pourrait revoir le choix.
$rendered = wp_kses_post( '<img class="wp-image-42" src="https://x/a.jpg" alt="" width="800" height="600" srcset="https://x/a.jpg 800w" sizes="100vw" decoding="async" />' );
AOD_Test::ok( false === strpos( $rendered, 'srcset' ), 'kses STRIPPE srcset (→ régénéré au rendu depuis wp-image-{id}, pas stocké)', $rendered );
AOD_Test::ok( false === strpos( $rendered, 'sizes' ), 'kses STRIPPE sizes (idem)', $rendered );
AOD_Test::ok( false === strpos( $rendered, 'decoding' ), 'kses STRIPPE decoding (idem)', $rendered );
AOD_Test::ok( false !== strpos( $rendered, 'wp-image-42' ), 'kses CONSERVE la classe wp-image-{id} — c\'est ELLE qui déclenche le srcset au rendu', $rendered );

AOD_Test::group( 'Description riche — où kses N\'EST PAS la barrière (le garde-fou vit côté client)' );

// ⚠️ Honnêteté du contrat : la barre ne propose ni couleur ni police, mais
// wp_kses_post CONSERVE color/font-family/font-size (safe_style_css les autorise).
// La barrière contre un COLLAGE qui les apporterait n'est donc PAS kses — c'est
// `valid_styles: { '*': 'text-align' }` dans l'init TinyMCE (dashboard.js). Ce test
// documente la frontière, pour que personne ne croie kses capable de bloquer ça.
$out = wp_kses_post( '<p style="color:red;font-family:Arial;font-size:40px">x</p>' );
AOD_Test::ok(
	false !== strpos( $out, 'color' ),
	'kses NE bloque PAS color/font inline (→ la barrière est valid_styles, côté éditeur, pas le serveur)',
	$out
);

AOD_Test::group( 'Description riche — parité FR/AR et discriminance des tests' );

// Le couple : l'AR subit EXACTEMENT le même kses que le FR (7.2-D3 — pas de
// sanitize_textarea_field qui dépouillerait l'arabe de sa mise en forme).
$ar = '<h2>عنوان</h2><ul><li>ميزة <strong>مهمة</strong></li></ul>';
AOD_Test::ok( wp_kses_post( $ar ) === $ar, 'La description AR garde sa mise en forme à l\'identique', wp_kses_post( $ar ) );

// La preuve que les assertions ci-dessus DISCRIMINENT : le nettoyage « dur »
// mutile chaque cas de la grammaire. Si un futur refactor remplaçait kses par
// sanitize_textarea_field, tous les tests du 1er groupe tomberaient — et
// celui-ci documente pourquoi.
$mutilated = 0;
foreach ( $grammar as $case ) {
	if ( sanitize_textarea_field( $case[0] ) !== $case[1] ) {
		++$mutilated;
	}
}
AOD_Test::ok(
	count( $grammar ) === $mutilated,
	'Discriminance : sanitize_textarea_field mutilerait les ' . count( $grammar ) . ' cas de la grammaire (nos tests tomberaient sur ce mauvais code)',
	"seulement {$mutilated} cas mutilés"
);
