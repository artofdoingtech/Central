<?php
/**
 * Page de vente (Phase 6) — schéma fermé, couple { fr, ar }, et les trois
 * invariants de sécurité annoncés par `docs/LANDING-PRODUIT.md` §2 :
 *   1. aucun HTML libre ne survit à la saisie du gérant ;
 *   2. une image est un ID d'attachement, jamais une URL ;
 *   3. un lien n'est jamais réinjecté brut (l'ID YouTube est extrait et revalidé).
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Landing — couple bilingue { fr, ar }' );

AOD_Test::eq( 'Bonjour', AOD_COD_Landing::text( array( 'fr' => 'Bonjour', 'ar' => 'مرحبا' ) ), 'En FR, le texte français est rendu' );
AOD_Test::eq( 'مرحبا', AOD_Test::in_arabic( static function () {
	return AOD_COD_Landing::text( array( 'fr' => 'Bonjour', 'ar' => 'مرحبا' ) );
} ), 'En AR, le texte arabe est rendu' );

// Le repli doit jouer DANS LES DEUX SENS : un gérant qui ne remplit qu'une langue
// ne doit jamais produire un bloc vide — c'est ce qui distingue text() de label().
AOD_Test::eq( 'Bonjour', AOD_Test::in_arabic( static function () {
	return AOD_COD_Landing::text( array( 'fr' => 'Bonjour', 'ar' => '' ) );
} ), 'En AR sans traduction, on retombe sur le français (pas de vide)' );
AOD_Test::eq( 'مرحبا', AOD_COD_Landing::text( array( 'fr' => '', 'ar' => 'مرحبا' ) ), 'En FR sans français, on retombe sur l\'arabe' );

AOD_Test::eq( '', AOD_COD_Landing::text( 'une chaîne simple' ), 'Une chaîne unique (pas un couple) ne rend rien — le contenu marchand DOIT porter ses deux langues' );
AOD_Test::eq( '', AOD_COD_Landing::text( null ), 'Un champ absent rend une chaîne vide' );

AOD_Test::group( 'Landing — extraction de l\'identifiant YouTube' );

$ok = array(
	'https://www.youtube.com/watch?v=dQw4w9WgXcQ'          => 'dQw4w9WgXcQ',
	'https://youtu.be/dQw4w9WgXcQ'                          => 'dQw4w9WgXcQ',
	'https://www.youtube.com/embed/dQw4w9WgXcQ'             => 'dQw4w9WgXcQ',
	'https://www.youtube.com/shorts/dQw4w9WgXcQ'            => 'dQw4w9WgXcQ',
	'https://m.youtube.com/watch?v=dQw4w9WgXcQ&t=42s'       => 'dQw4w9WgXcQ',
	'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0' => 'dQw4w9WgXcQ',
);
foreach ( $ok as $url => $expected ) {
	AOD_Test::eq( $expected, AOD_COD_Landing::youtube_id( $url ), "ID extrait de {$url}" );
}

// Tout ce qui n'est pas une vidéo YouTube valide doit rendre '' — donc AUCUNE iframe.
$ko = array(
	'javascript:alert(1)',
	'https://evil.tld/watch?v=dQw4w9WgXcQ',
	'https://www.youtube.com/watch?v=trop_court',
	'https://vimeo.com/123456789',
	'pas une url du tout',
	'',
);
foreach ( $ko as $url ) {
	AOD_Test::eq( '', AOD_COD_Landing::youtube_id( $url ), "Rejeté : '{$url}'" );
}

AOD_Test::group( 'Landing — sanitize() : le schéma est fermé' );

$data = AOD_COD_Landing::sanitize( array(
	'blocks' => array(
		array( 'type' => 'hero', 'title' => array( 'fr' => 'Titre', 'ar' => 'عنوان' ) ),
		array( 'type' => 'block_qui_nexiste_pas', 'title' => array( 'fr' => 'Pirate' ) ),
		array( 'type' => 'text', 'text' => array( 'fr' => 'Corps' ) ),
	),
) );
AOD_Test::eq( 2, count( $data['blocks'] ), 'Un type de bloc hors catalogue est ignoré (2 blocs sur 3 retenus)' );
AOD_Test::eq( 'hero', $data['blocks'][0]['type'], 'Le premier bloc retenu est bien le héro' );
AOD_Test::eq( 'text', $data['blocks'][1]['type'], 'Le bloc pirate a disparu, le bloc texte suit directement' );

$junk = AOD_COD_Landing::sanitize( 'n\'importe quoi' );
AOD_Test::eq( array(), $junk['blocks'], 'Une donnée non-tableau rend une landing sans blocs, jamais une erreur' );
AOD_Test::ok( false === $junk['sticky_cta']['enabled'], 'Une donnée non-tableau laisse la barre collante désactivée' );

// Chaque bloc doit sortir avec un id unique : les ancres et le réordonnancement en
// dépendent. Deux blocs qui arrivent avec le MÊME id ne doivent pas le rester.
$dup = AOD_COD_Landing::sanitize( array(
	'blocks' => array(
		array( 'type' => 'text', 'id' => 'meme-id' ),
		array( 'type' => 'text', 'id' => 'meme-id' ),
		array( 'type' => 'text', 'id' => 'meme-id' ),
	),
) );
$ids = wp_list_pluck( $dup['blocks'], 'id' );
AOD_Test::eq( 3, count( array_unique( $ids ) ), 'Trois blocs au même id ressortent avec trois ids distincts' );

AOD_Test::group( 'Landing — sanitize() : aucun HTML libre ne survit' );

$xss = AOD_COD_Landing::sanitize( array(
	'blocks' => array(
		array(
			'type'  => 'hero',
			'title' => array( 'fr' => '<script>alert(1)</script>Titre', 'ar' => '<img src=x onerror=alert(1)>' ),
			'text'  => array( 'fr' => 'Avant<script>alert(2)</script>Après' ),
		),
	),
) );
$t = $xss['blocks'][0]['title'];
AOD_Test::ok( false === strpos( $t['fr'], '<script' ) && false === strpos( $t['fr'], 'alert' ), 'Une balise <script> dans le titre FR est retirée', 'obtenu : ' . $t['fr'] );
AOD_Test::ok( false === strpos( $t['ar'], '<img' ) && false === strpos( $t['ar'], 'onerror' ), 'Un <img onerror> dans le titre AR est retiré', 'obtenu : ' . $t['ar'] );
AOD_Test::ok( false === strpos( $xss['blocks'][0]['text']['fr'], '<script' ), 'Une balise <script> dans l\'accroche est retirée' );

AOD_Test::group( 'Landing — sanitize() : liens et images' );

$url = AOD_COD_Landing::sanitize( array(
	'blocks' => array( array( 'type' => 'video', 'url' => 'javascript:alert(1)' ) ),
) );
AOD_Test::eq( '', $url['blocks'][0]['url'], 'Un lien `javascript:` est vidé (whitelist http/https)' );

$url2 = AOD_COD_Landing::sanitize( array(
	'blocks' => array( array( 'type' => 'video', 'url' => 'https://youtu.be/dQw4w9WgXcQ' ) ),
) );
AOD_Test::eq( 'https://youtu.be/dQw4w9WgXcQ', $url2['blocks'][0]['url'], 'Un lien https légitime est conservé' );

$img = AOD_COD_Landing::sanitize( array(
	'blocks' => array(
		array(
			'type'   => 'gallery',
			'images' => array( '12', 'https://evil.tld/x.png', 0, -5, '12', 34 ),
		),
	),
) );
AOD_Test::eq( array( 12, 34 ), $img['blocks'][0]['images'], 'Une galerie ne garde que des IDs d\'attachement positifs et dédoublonnés — jamais une URL' );

$hero_img = AOD_COD_Landing::sanitize( array(
	'blocks' => array( array( 'type' => 'hero', 'image' => 'https://evil.tld/x.png' ) ),
) );
AOD_Test::eq( 0, $hero_img['blocks'][0]['image'], 'Une URL passée dans un champ image devient 0 (aucune image), pas un lien externe' );
