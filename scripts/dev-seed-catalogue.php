<?php
/**
 * Catalogue de DÉMONSTRATION — deux produits complets, bilingues, avec page de vente.
 *
 * Créer :  docker compose exec -T wpcli wp eval-file /scripts/dev-seed-catalogue.php create
 * Purger : docker compose exec -T wpcli wp eval-file /scripts/dev-seed-catalogue.php cleanup
 *
 * Prérequis (les visuels) :  bash scripts/dev-assets-demo.sh   ← SUR L'HÔTE, une fois.
 * Sans eux, le seed crée quand même les produits, mais sans images (et le dit).
 *
 * Ce qu'il sème :
 *   · 2 catégories bilingues, mises en avant sur l'accueil (Phase 9) ;
 *   · 2 produits « vrais » — casque audio sans fil, sac à dos urbain — avec
 *     titre/description/arguments FR **et** AR, prix + promo, prix d'achat, SKU,
 *     poids, image principale + galerie, offres par quantité, et surtout
 *     **3 variantes de couleur CHACUNE AVEC SON PROPRE STOCK** (`_aod_options`,
 *     Phase 40.1 : `''` = non géré, un entier = géré, `0` = rupture) ;
 *   · 2 pages de vente complètes (héro, bénéfices, galerie, comparatif/texte,
 *     avis, FAQ, urgence, formulaire COD, réassurance + barre collante), ALLUMÉES.
 *
 * ⚠️ CE N'EST PAS DU CONTENU DE TEMPLATE. Rien ici n'est installé par `setup.sh`
 * et rien n'est spécifique à un client livré : c'est un jeu d'essai, au même titre
 * que `dev-seed.php` et les `recette-seed-*.php`. Il sert à éprouver la chaîne
 * commande → confirmation → assignation sur des fiches réalistes, pas squelettiques.
 *
 * ⚠️ La page de vente REMPLACE le rendu de la fiche produit (`template_include`,
 * cf. docs/LANDING-PRODUIT.md §3). Les descriptions riches ci-dessous ne
 * s'affichent donc pas tant que l'interrupteur est allumé : pour les voir,
 * éteindre « Page de vente » depuis `/gestion → Produits`. Elles restent en base,
 * intactes, et alimentent `/gestion` ainsi que le rendu classique.
 *
 * Tout ce qui est créé porte la marque `_aod_seed_catalogue` = 1 (produits,
 * attachements) ou la méta de terme du même nom (catégories). `cleanup` ne
 * supprime QUE ça — des données réelles créées à côté survivent.
 *
 * @package AOD
 */

defined( 'ABSPATH' ) || exit;

if ( false === strpos( home_url(), 'localhost' ) && false === strpos( home_url(), '127.0.0.1' ) ) {
	WP_CLI::error( 'Refus : ce script est réservé au dev local (home_url = ' . home_url() . ').' );
}
if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'AOD_COD_Landing' ) ) {
	WP_CLI::error( 'WooCommerce et/ou aod-cod-form ne sont pas actifs.' );
}

const AOD_DEMO_MARK = '_aod_seed_catalogue';

/**
 * Où lire les visuels, dans le conteneur.
 *
 * `/library` est le chemin nominal (montage `./library:/library:ro`). Mais ce
 * montage est ÉPINGLÉ SUR L'INODE du dossier hôte au démarrage du conteneur : si
 * `library/` a été recréé depuis (clone frais, dépôt re-cloné, dossier supprimé
 * puis refait), le conteneur continue de voir l'ancien — vide — sans la moindre
 * erreur. On replie alors sur `/repo/library`, la vue lecture seule du dépôt
 * entier (montée pour le cliquet documentaire 32.6), qui n'a pas ce problème.
 *
 * @return string Chemin du dossier des visuels (le nominal si aucun ne convient).
 */
function aod_demo_assets_dir() {
	foreach ( array( '/library/demo/produits', '/repo/library/demo/produits' ) as $dir ) {
		if ( is_dir( $dir ) && glob( $dir . '/*.jpg' ) ) {
			return $dir;
		}
	}
	return '/library/demo/produits';
}

/* =========================================================================
 * PURGE
 * ====================================================================== */

/**
 * Supprime tout ce qui porte la marque. Idempotent : rejouable après un Ctrl-C.
 *
 * Ordre voulu : produits d'abord (ils référencent les attachements), puis les
 * attachements, puis les catégories (vidées de leurs produits, donc supprimables
 * sans laisser de terme fantôme sur l'accueil).
 *
 * @return array{0:int,1:int,2:int} produits, attachements, catégories.
 */
function aod_demo_cleanup() {
	$n_p = 0;
	foreach ( get_posts( array(
		'post_type'   => 'product',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => AOD_DEMO_MARK,
		'meta_value'  => '1',
	) ) as $pid ) {
		wp_delete_post( $pid, true );
		$n_p++;
	}

	$n_a = 0;
	foreach ( get_posts( array(
		'post_type'   => 'attachment',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => AOD_DEMO_MARK,
		'meta_value'  => '1',
	) ) as $aid ) {
		wp_delete_attachment( $aid, true );
		$n_a++;
	}

	$n_t = 0;
	foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'ids' ) ) as $tid ) {
		if ( get_term_meta( $tid, AOD_DEMO_MARK, true ) ) {
			wp_delete_term( $tid, 'product_cat' );
			$n_t++;
		}
	}

	return array( $n_p, $n_a, $n_t );
}

$aod_demo_mode = isset( $args[0] ) ? $args[0] : '';

if ( 'cleanup' === $aod_demo_mode ) {
	list( $n_p, $n_a, $n_t ) = aod_demo_cleanup();
	WP_CLI::success( "Catalogue de démo purgé : $n_p produit(s), $n_a image(s), $n_t catégorie(s)." );
	return;
}
if ( 'create' !== $aod_demo_mode ) {
	WP_CLI::error( 'Usage : wp eval-file /scripts/dev-seed-catalogue.php create|cleanup' );
}

aod_demo_cleanup(); // Reliquat d'une exécution interrompue : on repart propre.

/* =========================================================================
 * IMAGES
 * ====================================================================== */

/**
 * Importe `<nom>.jpg` de /library/demo/produits en médiathèque et rend son ID.
 *
 * `library/` est monté EN LECTURE SEULE : on ne peut pas y laisser WordPress
 * déplacer le fichier. On lit donc l'octet et on le réécrit dans `uploads/` via
 * `wp_upload_bits()` — c'est aussi ce qui garantit que l'attachement a bien ses
 * tailles dérivées (donc le `srcset` des galeries et de la landing).
 *
 * Mémorisé par nom : `casque-noir` sert à la fois d'image de variante et de photo
 * de galerie ; l'importer deux fois créerait deux médias pour un seul fichier.
 *
 * @param string $name Nom de fichier SANS extension (contrat avec dev-assets-demo.sh).
 * @param string $alt  Texte alternatif (accessibilité — et il compte : c'est ce
 *                     qu'un lecteur d'écran annonce à la place de la photo).
 * @return int ID d'attachement, ou 0 si le fichier est absent.
 */
function aod_demo_image( $name, $alt = '' ) {
	static $cache = array();
	if ( isset( $cache[ $name ] ) ) {
		return $cache[ $name ];
	}

	$path = aod_demo_assets_dir() . '/' . $name . '.jpg';
	if ( ! is_readable( $path ) ) {
		$cache[ $name ] = 0;
		WP_CLI::warning( "Visuel absent : $path — lance `bash scripts/dev-assets-demo.sh` sur l'hôte." );
		return 0;
	}

	$bits = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$up   = wp_upload_bits( $name . '.jpg', null, $bits );
	if ( ! empty( $up['error'] ) ) {
		$cache[ $name ] = 0;
		WP_CLI::warning( "Téléversement impossible ($name) : " . $up['error'] );
		return 0;
	}

	$att_id = wp_insert_attachment( array(
		'post_mime_type' => 'image/jpeg',
		'post_title'     => '' !== $alt ? $alt : $name,
		'post_content'   => '',
		'post_status'    => 'inherit',
	), $up['file'] );
	if ( ! $att_id || is_wp_error( $att_id ) ) {
		$cache[ $name ] = 0;
		WP_CLI::warning( "Attachement impossible ($name)." );
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $up['file'] ) );
	update_post_meta( $att_id, '_wp_attachment_image_alt', '' !== $alt ? $alt : $name );
	update_post_meta( $att_id, AOD_DEMO_MARK, '1' );

	$cache[ $name ] = (int) $att_id;
	return (int) $att_id;
}

/** Couple bilingue — le contrat de toute la landing (docs/LANDING-PRODUIT.md §4.2). */
function aod_demo_pair( $fr, $ar ) {
	return array( 'fr' => $fr, 'ar' => $ar );
}

/* =========================================================================
 * CATÉGORIES
 * ====================================================================== */

/**
 * Crée une catégorie bilingue mise en avant sur l'accueil.
 *
 * @param string $fr    Nom français (canonique : c'est lui que WordPress stocke).
 * @param string $ar    Nom arabe (`_aod_cat_name_ar`, Phase 7.5).
 * @param int    $order Rang dans « Nos catégories » (`_aod_cat_home_order`, Phase 9).
 * @param int    $thumb Vignette.
 * @return int term_id
 */
function aod_demo_category( $fr, $ar, $order, $thumb ) {
	$t = wp_insert_term( $fr, 'product_cat' );
	if ( is_wp_error( $t ) ) {
		// Une catégorie du même nom existe déjà (créée à la main) : on la réutilise
		// SANS la marquer — la purge ne doit jamais supprimer ce qu'elle n'a pas créé.
		$existing = get_term_by( 'name', $fr, 'product_cat' );
		if ( ! $existing ) {
			WP_CLI::error( "Catégorie « $fr » impossible : " . $t->get_error_message() );
		}
		return (int) $existing->term_id;
	}
	$tid = (int) $t['term_id'];
	update_term_meta( $tid, '_aod_cat_name_ar', $ar );
	update_term_meta( $tid, '_aod_cat_home', '1' );
	update_term_meta( $tid, '_aod_cat_home_order', (string) $order );
	if ( $thumb ) {
		update_term_meta( $tid, 'thumbnail_id', $thumb );
	}
	update_term_meta( $tid, AOD_DEMO_MARK, '1' );
	return $tid;
}

/* =========================================================================
 * PRODUIT 1 — Casque audio sans fil « AURA ANC 40 »
 * ====================================================================== */

$img = array(
	'casque_hero'  => aod_demo_image( 'casque-hero', 'Jeune femme allongée écoutant de la musique au casque' ),
	'casque_noir'  => aod_demo_image( 'casque-noir', 'Casque AURA ANC 40 coloris Noir Onyx, vu de face' ),
	'casque_blanc' => aod_demo_image( 'casque-blanc', 'Casque AURA ANC 40 coloris Blanc Perle porté autour du cou' ),
	'casque_bdx'   => aod_demo_image( 'casque-bordeaux', 'Casque AURA ANC 40 coloris Bordeaux posé sur un bureau en bois' ),
	'casque_d1'    => aod_demo_image( 'casque-detail-1', 'Gros plan sur les coussinets à mémoire de forme du AURA ANC 40' ),
	'casque_d2'    => aod_demo_image( 'casque-detail-2', 'Arceau et charnières pliantes du casque AURA ANC 40' ),
	'casque_d3'    => aod_demo_image( 'casque-detail-3', 'Casque AURA ANC 40 posé sur un ordinateur portable' ),
	'casque_d4'    => aod_demo_image( 'casque-detail-4', 'Casque AURA ANC 40 replié sur un plan de travail' ),
	'casque_l1'    => aod_demo_image( 'casque-lifestyle-1', 'Casque AURA ANC 40 porté autour du cou en extérieur' ),
	'casque_l2'    => aod_demo_image( 'casque-lifestyle-2', 'Casque AURA ANC 40 utilisé avec un smartphone' ),

	'sac_hero'     => aod_demo_image( 'sac-hero', 'Jeune femme adossée à un mur coloré avec le sac NOMAD 25L' ),
	'sac_noir'     => aod_demo_image( 'sac-noir', 'Sac à dos NOMAD 25L coloris Noir Carbone porté en ville' ),
	'sac_olive'    => aod_demo_image( 'sac-olive', 'Sac à dos NOMAD 25L coloris Vert Olive tenu à la main' ),
	'sac_moutarde' => aod_demo_image( 'sac-moutarde', 'Sac à dos NOMAD 25L coloris Jaune Moutarde porté à l’épaule' ),
	'sac_int'      => aod_demo_image( 'sac-interieur', 'Intérieur compartimenté du sac NOMAD 25L, matériel rangé' ),
	'sac_voyage'   => aod_demo_image( 'sac-voyage', 'Sac NOMAD 25L chargé pour un départ en voyage' ),
	'sac_flat'     => aod_demo_image( 'sac-flatlay', 'Sac NOMAD 25L posé à plat avec des accessoires' ),
	'sac_duo'      => aod_demo_image( 'sac-duo', 'Deux sacs NOMAD 25L côte à côte' ),
	'sac_life'     => aod_demo_image( 'sac-lifestyle', 'Homme assis, sac NOMAD 25L sur les genoux' ),
	'sac_contenu'  => aod_demo_image( 'sac-contenu', 'Contenu du sac NOMAD 25L étalé sur fond noir' ),
);

$cat_audio = aod_demo_category( 'Audio & High-Tech', 'الصوتيات والتكنولوجيا', 1, $img['casque_noir'] );
$cat_bagag = aod_demo_category( 'Bagagerie & Voyage', 'الحقائب والسفر', 2, $img['sac_moutarde'] );

/* --- Description riche FR / AR -------------------------------------------
 * `wp_kses_post` autorise ce HTML. Les <img> portent `class="wp-image-<id>"` :
 * c'est ce qui déclenche le `srcset` + le `loading="lazy"` du cœur WordPress.
 * ---------------------------------------------------------------------- */

$casque_img_tag = $img['casque_d1']
	? '<figure class="wp-block-image size-large"><img class="wp-image-' . $img['casque_d1'] . '" src="'
		. esc_url( wp_get_attachment_image_url( $img['casque_d1'], 'large' ) )
		. '" alt="Gros plan sur les coussinets à mémoire de forme du AURA ANC 40" />'
		. '<figcaption>Coussinets à mémoire de forme, recouverts de similicuir protéiné.</figcaption></figure>'
	: '';

$casque_desc_fr = '
<h2>Le silence d’abord. La musique ensuite.</h2>
<p>Le <strong>AURA ANC 40</strong> est un casque circum-auriculaire sans fil conçu pour une seule chose : vous rendre votre attention. Ses quatre microphones mesurent le bruit ambiant 40 000 fois par seconde et le compensent en temps réel — le moteur d’un bus, la climatisation d’un open-space, le brouhaha d’un café disparaissent, sans que le son ne perde son relief.</p>
' . $casque_img_tag . '
<h3>Ce que vous entendez vraiment</h3>
<p>Deux transducteurs de 40 mm à membrane composite, accordés pour rester lisibles à fort volume. Les basses descendent bas sans envahir la voix ; les aigus restent nets après deux heures d’écoute. Le mode <strong>Transparence</strong> laisse repasser l’extérieur d’un appui long — pratique pour commander un café ou traverser une rue sans retirer le casque.</p>
<h3>Une autonomie qui ne se planifie pas</h3>
<ul>
	<li><strong>40 heures</strong> de lecture avec la réduction de bruit active, 60 h sans.</li>
	<li><strong>10 minutes</strong> de charge en USB-C donnent <strong>5 heures</strong> d’écoute.</li>
	<li>Extinction automatique après 10 minutes sans signal : le casque ne se vide pas dans le sac.</li>
</ul>
<h3>Fiche technique</h3>
<ul>
	<li>Transducteurs : 40 mm, membrane composite</li>
	<li>Réduction de bruit active : hybride, jusqu’à −35 dB</li>
	<li>Bluetooth 5.3, multipoint (2 appareils simultanés), codecs SBC / AAC / LDAC</li>
	<li>Micros : 4, avec réduction de bruit à l’émission</li>
	<li>Autonomie : 40 h (ANC activée) · charge complète en 2 h</li>
	<li>Poids : 265 g · arceau pliant · étui rigide et câble jack 3,5 mm fournis</li>
	<li>Garantie : 12 mois</li>
</ul>
<h3>Dans la boîte</h3>
<p>Le casque, un étui rigide de transport, un câble USB-C, un câble jack 3,5 mm pour l’écoute filaire, et le guide de démarrage en français et en arabe.</p>
';

$casque_desc_ar = '
<h2>الصمت أولاً، ثم الموسيقى.</h2>
<p>سماعة <strong>AURA ANC 40</strong> سماعة لاسلكية محيطة بالأذن صُمّمت لغاية واحدة: أن تعيد إليك تركيزك. أربعة مايكروفونات تقيس الضجيج المحيط 40 ألف مرة في الثانية وتعوّضه في الحين — محرك الحافلة، مكيّف المكتب، ضجيج المقهى… كلها تختفي، دون أن يفقد الصوت عمقه.</p>
<h3>ما تسمعه فعلاً</h3>
<p>وحدتا صوت بقطر 40 مم بغشاء مركّب، مضبوطتان لتبقيا واضحتين حتى عند رفع الصوت. الترددات المنخفضة عميقة دون أن تطغى على الصوت البشري، والعالية تبقى نقية بعد ساعتين من الاستماع. ويسمح وضع <strong>الشفافية</strong> بعودة أصوات المحيط بضغطة مطوّلة — عملي لطلب قهوة أو عبور الطريق دون نزع السماعة.</p>
<h3>استقلالية لا تحتاج إلى تخطيط</h3>
<ul>
	<li><strong>40 ساعة</strong> استماع مع إلغاء الضجيج، و60 ساعة بدونه.</li>
	<li><strong>10 دقائق</strong> شحن عبر USB-C تمنحك <strong>5 ساعات</strong> استماع.</li>
	<li>إطفاء تلقائي بعد 10 دقائق دون إشارة: لا تفرغ البطارية داخل الحقيبة.</li>
</ul>
<h3>البطاقة التقنية</h3>
<ul>
	<li>وحدات الصوت: 40 مم بغشاء مركّب</li>
	<li>إلغاء الضجيج النشط: هجين، حتى −35 ديسيبل</li>
	<li>بلوتوث 5.3 متعدد النقاط (جهازان في آن واحد)، ترميز SBC / AAC / LDAC</li>
	<li>المايكروفونات: أربعة، مع تنقية الصوت عند الإرسال</li>
	<li>الاستقلالية: 40 ساعة مع إلغاء الضجيج · شحن كامل في ساعتين</li>
	<li>الوزن: 265 غرام · هيكل قابل للطي · علبة صلبة وسلك 3.5 مم مرفقان</li>
	<li>الضمان: 12 شهراً</li>
</ul>
<h3>محتويات العلبة</h3>
<p>السماعة، علبة نقل صلبة، سلك USB-C، سلك 3.5 مم للاستماع السلكي، ودليل التشغيل بالفرنسية والعربية.</p>
';

$casque = new WC_Product_Simple();
$casque->set_name( 'Casque sans fil AURA ANC 40 — réduction de bruit active' );
$casque->set_slug( 'casque-aura-anc-40' );
$casque->set_status( 'publish' );
$casque->set_sku( 'AURA-ANC40' );
$casque->set_regular_price( '12900' );
$casque->set_sale_price( '8900' );
// Promo DATÉE : c'est elle — et elle seule — qui alimente le rebours du bloc
// « urgence » en mode `sale` (LANDING-PRODUIT.md §5 : le compteur lit la date en
// base, il n'invente rien et ne se réarme jamais).
$casque->set_date_on_sale_from( gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
$casque->set_date_on_sale_to( gmdate( 'Y-m-d H:i:s', time() + 6 * DAY_IN_SECONDS ) );
$casque->set_description( $casque_desc_fr );
$casque->set_short_description( 'Réduction de bruit active jusqu’à −35 dB, 40 h d’autonomie, Bluetooth 5.3 multipoint. Livré partout en Algérie, payé à la livraison.' );
$casque->set_weight( '0.42' );
$casque->set_category_ids( array( $cat_audio ) );
$casque->set_manage_stock( true );
// = 40 + 25 + 15, la somme des stocks de variante.
// ⚠️ Dimensionné pour ABSORBER `dev-seed-commandes.php` : une commande naît en
// `processing`, et WooCommerce décrémente le stock produit sur ce statut exact
// (`wc-stock-functions.php`). Trente commandes sur un stock de 12 le pousseraient
// dans le négatif — et `orderable_stock()` (44.1) refuserait les dernières.
$casque->set_stock_quantity( 80 );
$casque->set_low_stock_amount( 10 );
$casque->set_stock_status( 'instock' );
if ( $img['casque_noir'] ) {
	$casque->set_image_id( $img['casque_noir'] );
}
$casque->set_gallery_image_ids( array_values( array_filter( array(
	$img['casque_blanc'], $img['casque_bdx'], $img['casque_d1'], $img['casque_d3'], $img['casque_l1'],
) ) ) );

$casque->update_meta_data( '_aod_title_ar', 'سماعة لاسلكية AURA ANC 40 — إلغاء ضجيج نشط' );
$casque->update_meta_data( '_aod_desc_ar', $casque_desc_ar );
$casque->update_meta_data( '_aod_cost_price', '5200' );
$casque->update_meta_data( '_aod_selling_points', array(
	'Réduction de bruit active hybride jusqu’à −35 dB',
	'40 h d’autonomie — 10 min de charge = 5 h d’écoute',
	'Bluetooth 5.3 multipoint : téléphone et ordinateur en même temps',
	'265 g, arceau pliant, étui rigide fourni',
	'4 micros : on vous entend clairement, même dans la rue',
) );
$casque->update_meta_data( '_aod_selling_points_ar', array(
	'إلغاء ضجيج نشط هجين حتى −35 ديسيبل',
	'40 ساعة استقلالية — 10 دقائق شحن = 5 ساعات استماع',
	'بلوتوث 5.3 متعدد النقاط: الهاتف والحاسوب في آن واحد',
	'265 غرام، هيكل قابل للطي، مع علبة صلبة',
	'أربعة مايكروفونات: يسمعونك بوضوح حتى في الشارع',
) );

// ── Variantes : UN axe, TROIS couleurs, CHACUNE AVEC SON STOCK ─────────────
// `stock` : '' = non géré (illimité) · '0' = rupture (la valeur se désactive
// dans le formulaire) · entier > 0 = géré. On distingue TOUJOURS '' de 0.
// `price` est un SUPPLÉMENT ajouté au prix unitaire, pas un prix de remplacement.
$casque->update_meta_data( '_aod_options', array(
	array(
		'label'    => 'Couleur',
		'label_ar' => 'اللون',
		'visual'   => true,      // pastilles + photo qui change à la sélection
		'display'  => 'cards',
		'values'   => array(
			array( 'name' => 'Noir Onyx',   'name_ar' => 'أسود أونيكس',  'price' => '',    'image_id' => $img['casque_noir'],  'hex' => '#17181a', 'stock' => '40' ),
			array( 'name' => 'Blanc Perle', 'name_ar' => 'أبيض لؤلؤي',  'price' => '',    'image_id' => $img['casque_blanc'], 'hex' => '#efede8', 'stock' => '25' ),
			array( 'name' => 'Bordeaux',    'name_ar' => 'نبيذي',        'price' => '500', 'image_id' => $img['casque_bdx'],   'hex' => '#6e2b33', 'stock' => '15' ),
		),
	),
) );

// Offres : prix TOTAL du lot, et il doit rester sous prix normal × quantité
// (12 900 × 2 = 25 800 · × 3 = 38 700), sinon le dashboard les rejette.
$casque->update_meta_data( '_aod_offers', array(
	array( 'qty' => 2, 'price' => 16500 ),
	array( 'qty' => 3, 'price' => 23400 ),
) );
$casque->update_meta_data( AOD_DEMO_MARK, '1' );
$casque_id = $casque->save();

/* --- Page de vente du casque --------------------------------------------- */

$casque_landing = array(
	'sticky_cta' => array(
		'enabled' => true,
		'label'   => aod_demo_pair( 'Commander — 8 900 DA', 'اطلب الآن — 8900 دج' ),
	),
	'blocks' => array(
		array(
			'id'    => 'hero',
			'type'  => 'hero',
			'image' => $img['casque_hero'],
			'title' => aod_demo_pair(
				'Coupez le bruit. Gardez la musique.',
				'اقطع الضجيج. واحتفظ بالموسيقى.'
			),
			'text'  => aod_demo_pair(
				'AURA ANC 40 — réduction de bruit active jusqu’à −35 dB, 40 heures d’autonomie. Payez à la livraison, partout en Algérie.',
				'AURA ANC 40 — إلغاء ضجيج نشط حتى −35 ديسيبل، و40 ساعة استقلالية. ادفع عند الاستلام، في كامل الجزائر.'
			),
			'cta'   => aod_demo_pair( 'Je commande maintenant', 'أطلبها الآن' ),
		),
		array(
			'id'    => 'points',
			'type'  => 'points',
			'title' => aod_demo_pair( 'Pourquoi ce casque, et pas un autre', 'لماذا هذه السماعة بالذات' ),
			'items' => array(
				array(
					'icon'  => '🔇',
					'title' => aod_demo_pair( 'Le bruit s’arrête net', 'الضجيج يتوقف فوراً' ),
					'text'  => aod_demo_pair(
						'Quatre micros mesurent l’extérieur 40 000 fois par seconde. Le bus, le climatiseur, le café bondé : vous ne les entendez plus.',
						'أربعة مايكروفونات تقيس المحيط 40 ألف مرة في الثانية. الحافلة، المكيّف، المقهى المزدحم: لن تسمعها بعد الآن.'
					),
				),
				array(
					'icon'  => '🔋',
					'title' => aod_demo_pair( '40 heures, puis 5 h en 10 min', '40 ساعة، ثم 5 ساعات في 10 دقائق' ),
					'text'  => aod_demo_pair(
						'Une charge tient la semaine. Et si vous oubliez, dix minutes sur USB-C vous rendent une journée entière.',
						'شحنة واحدة تكفي الأسبوع. وإن نسيت، عشر دقائق على USB-C تعيد لك يوماً كاملاً.'
					),
				),
				array(
					'icon'  => '📱',
					'title' => aod_demo_pair( 'Deux appareils à la fois', 'جهازان في آن واحد' ),
					'text'  => aod_demo_pair(
						'Bluetooth 5.3 multipoint : le casque reste sur l’ordinateur et bascule tout seul quand le téléphone sonne.',
						'بلوتوث 5.3 متعدد النقاط: تبقى السماعة على الحاسوب وتنتقل وحدها عند رنين الهاتف.'
					),
				),
				array(
					'icon'  => '🪶',
					'title' => aod_demo_pair( '265 g qu’on oublie', '265 غراماً تُنسى' ),
					'text'  => aod_demo_pair(
						'Coussinets à mémoire de forme et arceau réglable : trois heures de vol sans point de pression.',
						'وسائد بذاكرة الشكل وهيكل قابل للتعديل: ثلاث ساعات طيران دون ضغط مؤلم.'
					),
				),
			),
		),
		array(
			'id'     => 'galerie',
			'type'   => 'gallery',
			'title'  => aod_demo_pair( 'Sous tous les angles', 'من كل الزوايا' ),
			'images' => array_values( array_filter( array(
				$img['casque_d1'], $img['casque_d2'], $img['casque_d3'], $img['casque_d4'], $img['casque_l2'],
			) ) ),
		),
		array(
			'id'    => 'compare',
			'type'  => 'compare',
			'title' => aod_demo_pair( 'AURA ANC 40 face à un casque d’entrée de gamme', 'AURA ANC 40 مقابل سماعة اقتصادية' ),
			'left'  => aod_demo_pair( 'AURA ANC 40', 'AURA ANC 40' ),
			'right' => aod_demo_pair( 'Casque à 3 000 DA', 'سماعة بـ3000 دج' ),
			'items' => array(
				array(
					'left'  => aod_demo_pair( 'Réduction de bruit active hybride, −35 dB', 'إلغاء ضجيج نشط هجين، −35 ديسيبل' ),
					'right' => aod_demo_pair( 'Aucune : seuls les coussinets isolent', 'لا يوجد: العزل من الوسائد فقط' ),
				),
				array(
					'left'  => aod_demo_pair( '40 h d’autonomie, charge rapide USB-C', '40 ساعة استقلالية، شحن سريع USB-C' ),
					'right' => aod_demo_pair( '8 à 10 h, micro-USB', 'من 8 إلى 10 ساعات، micro-USB' ),
				),
				array(
					'left'  => aod_demo_pair( 'Bluetooth 5.3 multipoint, LDAC', 'بلوتوث 5.3 متعدد النقاط، LDAC' ),
					'right' => aod_demo_pair( 'Bluetooth 5.0, un seul appareil', 'بلوتوث 5.0، جهاز واحد فقط' ),
				),
				array(
					'left'  => aod_demo_pair( 'Étui rigide et câble jack fournis', 'علبة صلبة وسلك 3.5 مم مرفقان' ),
					'right' => aod_demo_pair( 'Pochette en tissu, sans câble', 'كيس قماشي، دون سلك' ),
				),
				array(
					'left'  => aod_demo_pair( 'Garantie 12 mois, SAV en Algérie', 'ضمان 12 شهراً، خدمة ما بعد البيع في الجزائر' ),
					'right' => aod_demo_pair( 'Aucune garantie', 'دون ضمان' ),
				),
			),
		),
		array(
			'id'    => 'avis',
			'type'  => 'reviews',
			'title' => aod_demo_pair( 'Ce qu’en disent nos clients', 'ما يقوله زبائننا' ),
			'items' => array(
				array(
					'name'   => 'Yacine',
					'rating' => 5,
					'text'   => aod_demo_pair(
						'Je le porte dans le bus tous les matins entre Bab Ezzouar et le centre. On n’entend plus le moteur. La batterie tient toute la semaine.',
						'ألبسها في الحافلة كل صباح بين باب الزوار ووسط المدينة. لم يعد يُسمع المحرك. البطارية تدوم الأسبوع كاملاً.'
					),
					'photo'  => 0,
				),
				array(
					'name'   => 'Amina',
					'rating' => 5,
					'text'   => aod_demo_pair(
						'Commandé le dimanche, livré le mardi à Oran, payé au livreur. Le coloris Bordeaux est encore plus beau en vrai.',
						'طلبتها يوم الأحد، وصلتني الثلاثاء إلى وهران، ودفعت لعامل التوصيل. اللون النبيذي أجمل على الطبيعة.'
					),
					'photo'  => 0,
				),
				array(
					'name'   => 'Karim',
					'rating' => 4,
					'text'   => aod_demo_pair(
						'Excellent pour les appels en télétravail, mes collègues ne m’ont jamais aussi bien entendu. J’aurais aimé une housse un peu plus fine.',
						'ممتازة للمكالمات في العمل عن بُعد، لم يسمعني زملائي بهذا الوضوح من قبل. كنت أتمنى علبة أرقّ قليلاً.'
					),
					'photo'  => 0,
				),
			),
		),
		array(
			'id'    => 'faq',
			'type'  => 'faq',
			'title' => aod_demo_pair( 'Vos questions', 'أسئلتكم' ),
			'items' => array(
				array(
					'q' => aod_demo_pair( 'Comment se passe le paiement ?', 'كيف تتم عملية الدفع؟' ),
					'a' => aod_demo_pair(
						'Vous ne payez rien en ligne. Vous réglez en espèces au livreur, au moment où il vous remet le colis, après l’avoir vérifié.',
						'لا تدفع شيئاً عبر الإنترنت. تدفع نقداً لعامل التوصيل لحظة تسليمك الطرد، بعد معاينته.'
					),
				),
				array(
					'q' => aod_demo_pair( 'Livrez-vous dans ma wilaya ?', 'هل توصلون إلى ولايتي؟' ),
					'a' => aod_demo_pair(
						'Nous livrons dans les 58 wilayas, à domicile ou en point de retrait (stop desk). Le tarif s’affiche automatiquement dès que vous choisissez votre wilaya dans le formulaire.',
						'نوصل إلى 58 ولاية، إلى المنزل أو إلى مكتب الاستلام. يظهر سعر التوصيل تلقائياً بمجرد اختيار ولايتك في الاستمارة.'
					),
				),
				array(
					'q' => aod_demo_pair( 'En combien de temps suis-je livré ?', 'في كم من الوقت يصلني الطرد؟' ),
					'a' => aod_demo_pair(
						'Comptez 24 à 48 h pour Alger et le nord, 48 à 72 h pour le sud. Vous recevez un appel de confirmation avant l’expédition.',
						'من 24 إلى 48 ساعة للجزائر العاصمة والشمال، ومن 48 إلى 72 ساعة للجنوب. تصلك مكالمة تأكيد قبل الإرسال.'
					),
				),
				array(
					'q' => aod_demo_pair( 'Puis-je le retourner s’il ne me convient pas ?', 'هل يمكنني إرجاعها إن لم تناسبني؟' ),
					'a' => aod_demo_pair(
						'Oui. Vous pouvez ouvrir et essayer le casque devant le livreur. S’il ne vous convient pas, vous le rendez sur place et vous ne payez rien.',
						'نعم. يمكنك فتح السماعة وتجريبها أمام عامل التوصيل. إن لم تناسبك، تُرجعها في الحين ولا تدفع شيئاً.'
					),
				),
				array(
					'q' => aod_demo_pair( 'Le casque marche-t-il avec un câble ?', 'هل تعمل السماعة بالسلك؟' ),
					'a' => aod_demo_pair(
						'Oui, un câble jack 3,5 mm est fourni. Il permet d’écouter même batterie vide, et de la brancher sur un téléviseur ou un ampli.',
						'نعم، سلك 3.5 مم مرفق. يتيح الاستماع حتى مع نفاد البطارية، وربطها بالتلفاز أو المضخم.'
					),
				),
			),
		),
		array(
			'id'   => 'urgence',
			'type' => 'urgency',
			'mode' => 'sale', // rebours vers la fin réelle de la promo (date en base)
			'text' => aod_demo_pair(
				'Offre de lancement à 8 900 DA — elle se termine dans',
				'عرض الإطلاق بـ8900 دج — ينتهي خلال'
			),
			'max'  => 0,
		),
		array(
			'id'    => 'commander',
			'type'  => 'form',
			'title' => aod_demo_pair( 'Remplissez le formulaire, on vous rappelle', 'املأ الاستمارة، ونتصل بك' ),
		),
		array(
			'id'    => 'reassurance',
			'type'  => 'trust',
			'items' => array(
				array( 'icon' => '💵', 'text' => aod_demo_pair( 'Paiement à la livraison', 'الدفع عند الاستلام' ) ),
				array( 'icon' => '🚚', 'text' => aod_demo_pair( 'Livraison dans les 58 wilayas', 'التوصيل إلى 58 ولاية' ) ),
				array( 'icon' => '📦', 'text' => aod_demo_pair( 'Colis ouvert et vérifié devant vous', 'الطرد يُفتح ويُعاين أمامك' ) ),
				array( 'icon' => '🛡️', 'text' => aod_demo_pair( 'Garantie 12 mois', 'ضمان 12 شهراً' ) ),
			),
		),
	),
);

$casque->update_meta_data( '_aod_landing', AOD_COD_Landing::sanitize( $casque_landing ) );
$casque->update_meta_data( '_aod_landing_enabled', '1' ); // is_enabled() exige la CHAÎNE '1'.
$casque->save();

/* =========================================================================
 * PRODUIT 2 — Sac à dos urbain antivol « NOMAD 25L »
 * ====================================================================== */

$sac_img_tag = $img['sac_int']
	? '<figure class="wp-block-image size-large"><img class="wp-image-' . $img['sac_int'] . '" src="'
		. esc_url( wp_get_attachment_image_url( $img['sac_int'], 'large' ) )
		. '" alt="Intérieur compartimenté du sac NOMAD 25L" />'
		. '<figcaption>Sept rangements pensés pour qu’on ne vide plus le sac pour trouver ses clés.</figcaption></figure>'
	: '';

$sac_desc_fr = '
<h2>Un sac de ville qui tient le voyage</h2>
<p>Le <strong>NOMAD 25L</strong> a été dessiné pour la journée qui n’était pas prévue : le bureau le matin, la salle de sport le soir, l’aéroport le vendredi. Vingt-cinq litres, sept rangements, et une toile qui ne prend pas l’eau sous une averse d’octobre.</p>
' . $sac_img_tag . '
<h3>Ce qui est rangé reste rangé</h3>
<ul>
	<li>Compartiment ordinateur <strong>15,6"</strong> rembourré et suspendu — l’appareil ne touche jamais le fond du sac.</li>
	<li>Poche tablette séparée, poche avant à organisateur (stylos, chargeur, câbles).</li>
	<li>Poche cachée dans le dos, contre la colonne : portefeuille et passeport hors d’atteinte.</li>
	<li>Deux poches latérales extensibles pour la gourde et le parapluie.</li>
</ul>
<h3>Pensé contre les mauvaises surprises</h3>
<p>Les fermetures principales sont <strong>dissimulées</strong> derrière un rabat : elles ne s’ouvrent pas dans un bus bondé. Une poche intérieure <strong>anti-RFID</strong> bloque la lecture à distance de vos cartes. Le <strong>port USB extérieur</strong>, relié à une poche intérieure, permet de recharger le téléphone sans ouvrir le sac (batterie non fournie).</p>
<h3>Fiche technique</h3>
<ul>
	<li>Volume : 25 litres · dimensions 45 × 30 × 15 cm</li>
	<li>Matière : toile polyester 900D déperlante, doublure imperméable</li>
	<li>Ordinateur : jusqu’à 15,6 pouces · tablette jusqu’à 11 pouces</li>
	<li>Poids à vide : 850 g</li>
	<li>Bretelles ergonomiques matelassées, dos aéré, sangle de fixation trolley</li>
	<li>Fermetures YKK renforcées · port USB externe</li>
	<li>Garantie : 12 mois sur les coutures et les fermetures</li>
</ul>
';

$sac_desc_ar = '
<h2>حقيبة للمدينة تصمد في السفر</h2>
<p>صُمّمت <strong>NOMAD 25L</strong> لليوم الذي لم يكن في الحسبان: المكتب صباحاً، القاعة الرياضية مساءً، والمطار يوم الجمعة. خمسة وعشرون لتراً، وسبعة أماكن للترتيب، وقماش لا يبتلّ تحت مطر أكتوبر.</p>
<h3>ما رُتّب يبقى مرتّباً</h3>
<ul>
	<li>مقصورة مبطنة ومعلّقة لحاسوب <strong>15.6 بوصة</strong> — لا يلامس الجهاز قاع الحقيبة أبداً.</li>
	<li>جيب منفصل للوحي، وجيب أمامي منظّم (أقلام، شاحن، أسلاك).</li>
	<li>جيب مخفي في الظهر، ملاصق للعمود الفقري: المحفظة وجواز السفر بعيداً عن الأيدي.</li>
	<li>جيبان جانبيان قابلان للتمدد للقارورة والمظلة.</li>
</ul>
<h3>مصمّمة ضد المفاجآت السيئة</h3>
<p>السحّابات الرئيسية <strong>مخفية</strong> خلف غطاء: لا تُفتح في حافلة مزدحمة. وجيب داخلي <strong>مضاد لـ RFID</strong> يمنع قراءة بطاقاتك عن بُعد. أما <strong>منفذ USB الخارجي</strong>، الموصول بجيب داخلي، فيتيح شحن الهاتف دون فتح الحقيبة (البطارية غير مرفقة).</p>
<h3>البطاقة التقنية</h3>
<ul>
	<li>السعة: 25 لتراً · الأبعاد 45 × 30 × 15 سم</li>
	<li>المادة: قماش بوليستر 900D طارد للماء، بطانة عازلة</li>
	<li>الحاسوب: حتى 15.6 بوصة · اللوحي حتى 11 بوصة</li>
	<li>الوزن فارغة: 850 غراماً</li>
	<li>أحزمة مبطنة مريحة، ظهر مهوّى، وحزام تثبيت على مقبض حقيبة السفر</li>
	<li>سحّابات YKK معززة · منفذ USB خارجي</li>
	<li>الضمان: 12 شهراً على الخياطة والسحّابات</li>
</ul>
';

$sac = new WC_Product_Simple();
$sac->set_name( 'Sac à dos urbain antivol NOMAD 25L — port USB' );
$sac->set_slug( 'sac-a-dos-nomad-25l' );
$sac->set_status( 'publish' );
$sac->set_sku( 'NOMAD-25L' );
$sac->set_regular_price( '7500' );
$sac->set_sale_price( '5400' );
// Promo SANS date de fin : volontaire. C'est le témoin de l'autre moitié de la
// règle du bloc « urgence » — pas de date ⇒ pas de rebours. Ce produit affiche
// donc l'urgence par le STOCK, l'autre par le TEMPS.
$sac->set_description( $sac_desc_fr );
$sac->set_short_description( 'Compartiment ordinateur 15,6", poche anti-RFID, fermetures dissimulées et port USB. Toile déperlante 900D. Payé à la livraison.' );
$sac->set_weight( '0.85' );
$sac->set_category_ids( array( $cat_bagag ) );
$sac->set_manage_stock( true );
$sac->set_stock_quantity( 48 ); // = 24 + 16 + 8 (même dimensionnement que le casque).
$sac->set_low_stock_amount( 8 );
$sac->set_stock_status( 'instock' );
if ( $img['sac_moutarde'] ) {
	$sac->set_image_id( $img['sac_moutarde'] );
}
$sac->set_gallery_image_ids( array_values( array_filter( array(
	$img['sac_noir'], $img['sac_olive'], $img['sac_int'], $img['sac_flat'], $img['sac_voyage'],
) ) ) );

$sac->update_meta_data( '_aod_title_ar', 'حقيبة ظهر عصرية مضادة للسرقة NOMAD 25L — بمنفذ USB' );
$sac->update_meta_data( '_aod_desc_ar', $sac_desc_ar );
$sac->update_meta_data( '_aod_cost_price', '2600' );
$sac->update_meta_data( '_aod_selling_points', array(
	'Compartiment ordinateur 15,6" rembourré et suspendu',
	'Fermetures dissimulées + poche anti-RFID pour vos cartes',
	'Port USB extérieur : rechargez sans ouvrir le sac',
	'Toile 900D déperlante, coutures et fermetures renforcées',
	'Sangle trolley : il se pose sur la valise à l’aéroport',
) );
$sac->update_meta_data( '_aod_selling_points_ar', array(
	'مقصورة مبطنة ومعلّقة لحاسوب 15.6 بوصة',
	'سحّابات مخفية وجيب مضاد لـ RFID لبطاقاتك',
	'منفذ USB خارجي: اشحن دون فتح الحقيبة',
	'قماش 900D طارد للماء، بخياطة وسحّابات معززة',
	'حزام تثبيت: توضع فوق حقيبة السفر في المطار',
) );

// ── Variantes : UN axe, TROIS couleurs, CHACUNE AVEC SON STOCK ─────────────
// Jaune Moutarde à 2 pièces : c'est LUI qui déclenchera l'alerte de stock bas
// dans `/gestion → Stock`, et qui passera en rupture après une commande de 2.
$sac->update_meta_data( '_aod_options', array(
	array(
		'label'    => 'Couleur',
		'label_ar' => 'اللون',
		'visual'   => true,
		'display'  => 'cards',
		'values'   => array(
			array( 'name' => 'Noir Carbone',   'name_ar' => 'أسود كربوني', 'price' => '',    'image_id' => $img['sac_noir'],     'hex' => '#1b1c1e', 'stock' => '24' ),
			array( 'name' => 'Vert Olive',     'name_ar' => 'أخضر زيتوني', 'price' => '',    'image_id' => $img['sac_olive'],    'hex' => '#4e553f', 'stock' => '16' ),
			array( 'name' => 'Jaune Moutarde', 'name_ar' => 'أصفر خردلي',  'price' => '300', 'image_id' => $img['sac_moutarde'], 'hex' => '#d9a21b', 'stock' => '8' ),
		),
	),
) );

// 7 500 × 2 = 15 000 : l'offre à 9 900 passe le garde-fou du dashboard.
$sac->update_meta_data( '_aod_offers', array(
	array( 'qty' => 2, 'price' => 9900 ),
) );
$sac->update_meta_data( AOD_DEMO_MARK, '1' );
$sac_id = $sac->save();

/* --- Page de vente du sac ------------------------------------------------- */

$sac_landing = array(
	'sticky_cta' => array(
		'enabled' => true,
		'label'   => aod_demo_pair( 'Commander — 5 400 DA', 'اطلب الآن — 5400 دج' ),
	),
	'blocks' => array(
		array(
			'id'    => 'hero',
			'type'  => 'hero',
			'image' => $img['sac_hero'],
			'title' => aod_demo_pair(
				'Tout votre quotidien. Dans un seul sac.',
				'كل يومياتك في حقيبة واحدة.'
			),
			'text'  => aod_demo_pair(
				'NOMAD 25L — compartiment ordinateur 15,6", poche anti-RFID, port USB et toile déperlante. Payez à la livraison.',
				'NOMAD 25L — مقصورة لحاسوب 15.6 بوصة، جيب مضاد لـ RFID، منفذ USB وقماش طارد للماء. ادفع عند الاستلام.'
			),
			'cta'   => aod_demo_pair( 'Je le veux', 'أريدها' ),
		),
		array(
			'id'    => 'points',
			'type'  => 'points',
			'title' => aod_demo_pair( 'Quatre détails qui changent la journée', 'أربع تفاصيل تغيّر يومك' ),
			'items' => array(
				array(
					'icon'  => '💻',
					'title' => aod_demo_pair( 'Votre ordinateur ne touche rien', 'حاسوبك لا يلامس شيئاً' ),
					'text'  => aod_demo_pair(
						'Compartiment 15,6" rembourré et suspendu : même si vous posez le sac un peu vite, l’écran ne prend pas le choc.',
						'مقصورة 15.6 بوصة مبطنة ومعلّقة: حتى إن وضعت الحقيبة بسرعة، لا تتلقى الشاشة الصدمة.'
					),
				),
				array(
					'icon'  => '🔒',
					'title' => aod_demo_pair( 'Rien ne s’ouvre dans votre dos', 'لا شيء يُفتح خلف ظهرك' ),
					'text'  => aod_demo_pair(
						'Fermetures dissimulées sous un rabat et poche secrète plaquée contre le dos : le portefeuille reste où vous l’avez mis.',
						'سحّابات مخفية تحت غطاء وجيب سري ملاصق للظهر: تبقى المحفظة حيث وضعتها.'
					),
				),
				array(
					'icon'  => '🔌',
					'title' => aod_demo_pair( 'Recharge sans ouvrir', 'شحن دون فتح' ),
					'text'  => aod_demo_pair(
						'Un port USB sur le côté, relié à une poche intérieure : branchez votre batterie une fois, rechargez toute la journée.',
						'منفذ USB على الجانب موصول بجيب داخلي: اربط بطاريتك مرة واحدة، واشحن طوال اليوم.'
					),
				),
				array(
					'icon'  => '🌧️',
					'title' => aod_demo_pair( 'La pluie glisse dessus', 'المطر ينزلق عنها' ),
					'text'  => aod_demo_pair(
						'Toile polyester 900D déperlante et doublure imperméable : une averse ne traverse pas.',
						'قماش بوليستر 900D طارد للماء وبطانة عازلة: زخة مطر لا تخترقها.'
					),
				),
			),
		),
		array(
			'id'     => 'galerie',
			'type'   => 'gallery',
			'title'  => aod_demo_pair( 'En ville, en voyage, au bureau', 'في المدينة، في السفر، في المكتب' ),
			'images' => array_values( array_filter( array(
				$img['sac_int'], $img['sac_voyage'], $img['sac_flat'], $img['sac_duo'], $img['sac_life'], $img['sac_contenu'],
			) ) ),
		),
		array(
			'id'    => 'texte',
			'type'  => 'text',
			'title' => aod_demo_pair( '25 litres, et sept endroits où ranger', '25 لتراً، وسبعة أماكن للترتيب' ),
			'text'  => aod_demo_pair(
				"Le grand volume avale un pull, une paire de baskets et le déjeuner. La poche avant organise les câbles, les stylos et les clés. Les deux poches latérales prennent la gourde et le parapluie. Et la sangle arrière se glisse sur la poignée de la valise à l'aéroport.\n\nDimensions : 45 × 30 × 15 cm, 850 g à vide. Accepté en bagage cabine sur la plupart des vols intérieurs.",
				"الحيّز الكبير يبتلع سترة وحذاءً رياضياً ووجبة الغداء. الجيب الأمامي يرتّب الأسلاك والأقلام والمفاتيح. والجيبان الجانبيان يأخذان القارورة والمظلة. أما الحزام الخلفي فينزلق على مقبض حقيبة السفر في المطار.\n\nالأبعاد: 45 × 30 × 15 سم، و850 غراماً فارغة. مقبولة كأمتعة يد في معظم الرحلات الداخلية."
			),
		),
		array(
			'id'    => 'avis',
			'type'  => 'reviews',
			'title' => aod_demo_pair( 'Ils l’utilisent tous les jours', 'يستعملونها كل يوم' ),
			'items' => array(
				array(
					'name'   => 'Sofiane',
					'rating' => 5,
					'text'   => aod_demo_pair(
						'Je fais Blida–Alger en train chaque jour. Le PC est bien protégé et les fermetures cachées, c’est vraiment rassurant aux heures de pointe.',
						'أتنقل يومياً بين البليدة والجزائر بالقطار. الحاسوب محمي جيداً، والسحّابات المخفية مطمئنة فعلاً في ساعات الذروة.'
					),
					'photo'  => 0,
				),
				array(
					'name'   => 'Nadia',
					'rating' => 5,
					'text'   => aod_demo_pair(
						'Pris en Vert Olive pour la fac. Il a passé tout l’hiver sous la pluie, rien n’a mouillé à l’intérieur.',
						'أخذتها باللون الزيتوني للجامعة. قضت الشتاء كله تحت المطر، ولم يبتلّ شيء في الداخل.'
					),
					'photo'  => 0,
				),
				array(
					'name'   => 'Bilal',
					'rating' => 4,
					'text'   => aod_demo_pair(
						'Très bon sac pour le prix, la sangle sur la valise est bien pensée. Le port USB demande sa propre batterie, à savoir avant de commander.',
						'حقيبة ممتازة مقابل سعرها، وحزام التثبيت على الحقيبة فكرة موفقة. منفذ USB يحتاج بطاريته الخاصة، للعلم قبل الطلب.'
					),
					'photo'  => 0,
				),
			),
		),
		array(
			'id'    => 'faq',
			'type'  => 'faq',
			'title' => aod_demo_pair( 'Vos questions', 'أسئلتكم' ),
			'items' => array(
				array(
					'q' => aod_demo_pair( 'Le port USB fournit-il du courant tout seul ?', 'هل يوفّر منفذ USB الطاقة وحده؟' ),
					'a' => aod_demo_pair(
						'Non, et c’est important : c’est un simple passe-câble. Vous branchez VOTRE batterie externe dans la poche intérieure, et le port du côté vous évite d’ouvrir le sac.',
						'لا، وهذا مهم: إنه مجرد ممرّ للسلك. تضع بطاريتك الخارجية في الجيب الداخلي، ويعفيك المنفذ الجانبي من فتح الحقيبة.'
					),
				),
				array(
					'q' => aod_demo_pair( 'Passe-t-il en bagage cabine ?', 'هل تُقبل كأمتعة يد؟' ),
					'a' => aod_demo_pair(
						'45 × 30 × 15 cm : il entre dans le gabarit cabine de la plupart des compagnies sur les vols intérieurs. Vérifiez les dimensions de votre compagnie pour l’international.',
						'أبعادها 45 × 30 × 15 سم: تدخل في مقاس الأمتعة اليدوية لدى معظم الشركات في الرحلات الداخلية. تحقق من مقاسات شركتك في الرحلات الدولية.'
					),
				),
				array(
					'q' => aod_demo_pair( 'Comment se passe le paiement ?', 'كيف تتم عملية الدفع؟' ),
					'a' => aod_demo_pair(
						'Paiement à la livraison, en espèces, au moment où le livreur vous remet le sac. Rien à payer en ligne.',
						'الدفع عند الاستلام نقداً، لحظة تسليمك الحقيبة. لا شيء يُدفع عبر الإنترنت.'
					),
				),
				array(
					'q' => aod_demo_pair( 'Puis-je le voir avant de payer ?', 'هل يمكنني رؤيتها قبل الدفع؟' ),
					'a' => aod_demo_pair(
						'Oui. Ouvrez le colis devant le livreur et vérifiez le sac. S’il ne vous convient pas, vous le rendez et vous ne payez rien.',
						'نعم. افتح الطرد أمام عامل التوصيل وعاين الحقيبة. إن لم تناسبك، تُرجعها ولا تدفع شيئاً.'
					),
				),
			),
		),
		array(
			'id'   => 'urgence',
			'type' => 'urgency',
			'mode' => 'stock', // le nombre vient de get_stock_quantity() — jamais inventé
			'text' => aod_demo_pair(
				'Attention : il ne reste que %d sacs en stock',
				'انتبه: لم يتبقَّ سوى %d حقائب في المخزون'
			),
			'max'  => 60, // ne s'affiche que si le stock est ≤ 60 (ici 48 → visible)
		),
		array(
			'id'    => 'commander',
			'type'  => 'form',
			'title' => aod_demo_pair( 'Commandez en 30 secondes', 'اطلب في 30 ثانية' ),
		),
		array(
			'id'    => 'reassurance',
			'type'  => 'trust',
			'items' => array(
				array( 'icon' => '💵', 'text' => aod_demo_pair( 'Paiement à la livraison', 'الدفع عند الاستلام' ) ),
				array( 'icon' => '🚚', 'text' => aod_demo_pair( 'Livraison dans les 58 wilayas', 'التوصيل إلى 58 ولاية' ) ),
				array( 'icon' => '📦', 'text' => aod_demo_pair( 'Colis ouvert et vérifié devant vous', 'الطرد يُفتح ويُعاين أمامك' ) ),
				array( 'icon' => '🧵', 'text' => aod_demo_pair( 'Garantie 12 mois coutures et fermetures', 'ضمان 12 شهراً على الخياطة والسحّابات' ) ),
			),
		),
	),
);

$sac->update_meta_data( '_aod_landing', AOD_COD_Landing::sanitize( $sac_landing ) );
$sac->update_meta_data( '_aod_landing_enabled', '1' );
$sac->save();

/* =========================================================================
 * SORTIE
 * ====================================================================== */

if ( ! $casque_id || ! $sac_id ) {
	aod_demo_cleanup();
	WP_CLI::error( 'Création des produits impossible — fixtures purgées.' );
}

WP_CLI::log( 'CAT_AUDIO=' . $cat_audio );
WP_CLI::log( 'CAT_BAGAGERIE=' . $cat_bagag );
WP_CLI::log( 'CASQUE_PID=' . $casque_id );
WP_CLI::log( 'CASQUE_URL=' . get_permalink( $casque_id ) );
WP_CLI::log( 'SAC_PID=' . $sac_id );
WP_CLI::log( 'SAC_URL=' . get_permalink( $sac_id ) );
WP_CLI::success( 'Catalogue de démonstration créé : 2 catégories, 2 produits (3 variantes à stock propre chacun), 2 pages de vente allumées.' );
