<?php
/**
 * Vidéo dans la description produit (8.3) — la conversion « lien → façade » au rendu.
 *
 * Deux sources de vidéo, deux mécanismes OPPOSÉS, et c'est le cœur de l'étape :
 *
 *   • YouTube : `wp_kses_post()` SUPPRIME les <iframe> → on ne peut pas stocker de
 *     lecteur. On stocke un LIEN marqué (`<a class="aod-desc-video" href>`), et
 *     c'est le rendu public qui le convertit en FAÇADE cliquable (0 iframe, 0
 *     requête YouTube tant qu'on n'a pas cliqué — le pattern prouvé par la landing
 *     6.3). `render_facades()` est cette conversion, et ces tests la verrouillent.
 *
 *   • Fichier local : `<video src>` NATIF, que kses conserve — donc aucune
 *     conversion, aucun JS. Ce fichier-ci teste la seule logique qui a une logique :
 *     la façade YouTube. Le contrat kses du <video> vit dans test-desc-kses.php.
 *
 * Discriminance (leçon 7.x) : un lien ORDINAIRE vers YouTube (sans la classe) ne
 * doit PAS devenir une façade — la classe est le seul déclencheur ; et un ID
 * invalide ne doit RIEN injecter d'exploitable.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

$YT = 'dQw4w9WgXcQ'; // 11 caractères valides.

AOD_Test::group( 'Description vidéo — la façade YouTube (0 iframe avant le clic)' );

// Les 4 formats de lien que le gérant peut coller — tous doivent donner la MÊME façade.
$formats = array(
	'watch'  => 'https://www.youtube.com/watch?v=' . $YT,
	'court'  => 'https://youtu.be/' . $YT,
	'embed'  => 'https://www.youtube.com/embed/' . $YT,
	'shorts' => 'https://www.youtube.com/shorts/' . $YT,
);
foreach ( $formats as $nom => $url ) {
	$in  = '<p><a class="aod-desc-video" href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a></p>';
	$out = AOD_COD_Desc_Video::render_facades( $in );

	AOD_Test::ok( false !== strpos( $out, 'data-yt="' . $YT . '"' ), "Format {$nom} → façade avec le bon ID", $out );
	AOD_Test::ok( false !== strpos( $out, 'aod-landing__video-facade' ), "Format {$nom} → bouton façade réutilisant la classe landing", $out );
	AOD_Test::ok( false === strpos( $out, '<a class="aod-desc-video"' ), "Format {$nom} → le lien marqué a disparu (converti)", $out );
}

// 🔒 L'INVARIANT DUR : rien de tiers en sortie. L'iframe nocookie n'est bâtie qu'au
// clic, PAR LE JS (aod-landing.js), jamais dans le HTML servi.
$out = AOD_COD_Desc_Video::render_facades( '<p><a class="aod-desc-video" href="https://www.youtube.com/watch?v=' . $YT . '">x</a></p>' );
AOD_Test::ok( false === stripos( $out, '<iframe' ), 'Façade servie SANS <iframe> (0 lecteur avant le clic)', $out );
AOD_Test::ok( false === stripos( $out, 'youtube.com/embed' ) && false === stripos( $out, 'youtube-nocookie' ), 'Aucune URL d\'embed dans le HTML servi (bâtie au clic, côté JS)', $out );
AOD_Test::ok( false === stripos( $out, 'i.ytimg' ) && false === stripos( $out, 'img.youtube' ), 'Aucune miniature tierce (i.ytimg) — 0 requête YouTube au chargement', $out );

// Le repli sans JS : un <noscript> qui donne le lien en clair (jamais un bouton mort).
AOD_Test::ok( false !== stripos( $out, '<noscript' ) && false !== strpos( $out, 'watch?v=' . $YT ), 'Repli <noscript> : le lien YouTube reste donné en clair', $out );

AOD_Test::group( 'Description vidéo — dégradation gracieuse et discriminance' );

// Lien MARQUÉ mais NON-YouTube → laissé INTACT (lien inoffensif, jamais de lecteur mort).
$evil = '<p><a class="aod-desc-video" href="https://evil.example/x">clic</a></p>';
$out  = AOD_COD_Desc_Video::render_facades( $evil );
AOD_Test::ok( $out === $evil, 'Lien marqué non-YouTube → laissé tel quel (pas de façade)', $out );

// ID malformé (v=<script>) → youtube_id() rejette → rien d'exploitable injecté.
$bad = '<p><a class="aod-desc-video" href="https://www.youtube.com/watch?v=<script>">x</a></p>';
$out = AOD_COD_Desc_Video::render_facades( $bad );
AOD_Test::ok( false === strpos( $out, 'aod-landing__video-facade' ), 'ID malformé → aucune façade construite', $out );

// DISCRIMINANCE : un lien ORDINAIRE vers YouTube (SANS la classe) n'est PAS converti.
$plain = '<p><a href="https://www.youtube.com/watch?v=' . $YT . '">vidéo</a></p>';
$out   = AOD_COD_Desc_Video::render_facades( $plain );
AOD_Test::ok( $out === $plain, 'Discriminance : un lien sans la classe aod-desc-video reste un lien (la classe est le seul déclencheur)', $out );

// Absorption du <p> : wpautop enveloppe le lien ; la sortie ne doit pas laisser de
// <p> orphelin autour de la façade (HTML valide).
$out = AOD_COD_Desc_Video::render_facades( '<p><a class="aod-desc-video" href="https://youtu.be/' . $YT . '">u</a></p>' );
AOD_Test::ok( false === strpos( $out, '<p>' ) && false === strpos( $out, '</p>' ), 'Absorption du <p> englobant : pas de paragraphe orphelin autour de la façade', $out );
AOD_Test::ok( false === strpos( $out, '<div' ), 'Le wrap est un <span> (jamais un <div> qui serait invalide dans un <p> résiduel)', $out );

// Deux vidéos dans la même description → les deux converties.
$two = '<p><a class="aod-desc-video" href="https://youtu.be/' . $YT . '">a</a></p><p><a class="aod-desc-video" href="https://www.youtube.com/embed/' . $YT . '">b</a></p>';
$out = AOD_Test::call_static( 'AOD_COD_Desc_Video', 'render_facades', array( $two ) );
AOD_Test::eq( 2, substr_count( $out, 'aod-landing__video-facade' ), 'Deux liens marqués → deux façades' );

// 🩹 RÉGRESSION 8.4 (revue /code-review high de la 8.3) — le lien PARTAGE son
// paragraphe avec du texte. Le gérant tape « Regardez : » puis insère la vidéo au
// fil : `<p>Regardez : <a…></a></p>`. Le `<p>` ouvrant ne peut PAS être absorbé
// (du texte le précède) ; si le `</p>` fermant l'est quand même, le paragraphe
// devient orphelin et tout le contenu suivant est aspiré dedans. L'absorption
// doit être BALANCÉE : on ne retire le `</p>` que si on a retiré le `<p>`.
$mid = '<p>Regardez : <a class="aod-desc-video" href="https://www.youtube.com/watch?v=' . $YT . '">x</a></p>';
$out = AOD_COD_Desc_Video::render_facades( $mid );
AOD_Test::ok( false !== strpos( $out, 'aod-landing__video-facade' ), 'Lien en milieu de paragraphe → façade construite', $out );
AOD_Test::ok( false !== strpos( $out, 'Regardez :' ), 'Lien en milieu de paragraphe → le texte qui précède est conservé', $out );
AOD_Test::eq( substr_count( $out, '<p>' ), substr_count( $out, '</p>' ), 'Lien en milieu de paragraphe → HTML balancé (autant de <p> que de </p>, pas de paragraphe orphelin)' );

// 🩹 RÉGRESSION 8.4 (#3) — une description à vidéo-FICHIER seule ne doit pas être
// altérée (et le court-circuit ne doit pas se laisser piéger par le fait que
// `aod-desc-video` est un sous-ensemble de `aod-desc-video-file`).
$fileonly = '<video class="aod-desc-video-file" src="https://x/v.mp4" controls></video>';
AOD_Test::ok( $fileonly === AOD_COD_Desc_Video::render_facades( $fileonly ), 'Vidéo-fichier seule → rendu inchangé (pas de façade, court-circuit propre)' );

// …mais un fichier ET un lien YouTube dans la même description : le lien devient
// façade, le <video> fichier reste intact (le court-circuit resserré n'avale pas ce cas).
$mix = '<video class="aod-desc-video-file" src="https://x/v.mp4" controls></video>'
	. '<p><a class="aod-desc-video" href="https://youtu.be/' . $YT . '">v</a></p>';
$out = AOD_COD_Desc_Video::render_facades( $mix );
AOD_Test::ok( false !== strpos( $out, 'aod-landing__video-facade' ), 'Fichier + lien : le lien YouTube devient bien une façade', $out );
AOD_Test::ok( false !== strpos( $out, 'aod-desc-video-file' ), 'Fichier + lien : le <video> fichier natif reste intact', $out );

AOD_Test::group( 'Description vidéo — le contrat youtube_id() que le client (ytId) reflète (#1)' );

// La façade dérive TOUJOURS son ID de youtube_id() (serveur, source de vérité).
// L'éditeur (ytId, JS) doit accepter/refuser EXACTEMENT le même ensemble, sinon un
// lien valide est bloqué à la saisie, ou un lien invalide est inséré puis rendu
// inerte. Ces trois cas verrouillent le contrat serveur que le JS reflète.
AOD_Test::eq( $YT, AOD_COD_Landing::youtube_id( 'https://www.youtube-nocookie.com/embed/' . $YT ), 'youtube_id : l\'hôte nocookie (embed vie privée) est ACCEPTÉ' );
AOD_Test::eq( '', AOD_COD_Landing::youtube_id( 'https://www.youtube.com/watch?v=' . $YT . 'XYZ' ), 'youtube_id : un v= de plus de 11 caractères est REFUSÉ (pas de troncature)' );
AOD_Test::eq( '', AOD_COD_Landing::youtube_id( 'https://myyoutube.com/watch?v=' . $YT ), 'youtube_id : un hôte SOSIE (myyoutube.com) est REFUSÉ' );

AOD_Test::group( 'Description vidéo — has_video / has_video_file (pilotes de l\'enqueue conditionnel)' );

AOD_Test::ok( true === AOD_COD_Desc_Video::has_video( '<a class="aod-desc-video" href="https://youtu.be/' . $YT . '">x</a>' ), 'has_video : vrai sur un marqueur YouTube convertible' );
AOD_Test::ok( false === AOD_COD_Desc_Video::has_video( '<a class="aod-desc-video" href="https://evil.example/x">x</a>' ), 'has_video : faux sur un marqueur non-YouTube' );
AOD_Test::ok( false === AOD_COD_Desc_Video::has_video( '<a href="https://youtu.be/' . $YT . '">x</a>' ), 'has_video : faux sur un lien nu (sans la classe)' );

AOD_Test::ok( true === AOD_COD_Desc_Video::has_video_file( '<video class="aod-desc-video-file" src="https://x/v.mp4" controls></video>' ), 'has_video_file : vrai sur un <video> fichier' );
AOD_Test::ok( false === AOD_COD_Desc_Video::has_video_file( '<video src="https://x/v.mp4" controls></video>' ), 'has_video_file : faux sur un <video> sans notre classe' );
AOD_Test::ok( false === AOD_COD_Desc_Video::has_video_file( '<p>texte</p>' ), 'has_video_file : faux sans vidéo-fichier' );
