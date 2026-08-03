<?php
/**
 * Personnalisateur : couleur d'accent de l'espace de gestion /gestion.
 *
 * Règle template : rien de spécifique à un client en dur. L'accent (jaune par
 * défaut) est un réglage du Personnalisateur (`aod_cd_accent`). Toutes les
 * variantes utiles (survol, encre lisible, teinte douce) sont dérivées ici en
 * PHP puis injectées en ligne dans `:root` par render_app(). Le CSS ne contient
 * plus de couleur d'accent en dur — il ne lit que ces variables.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Customize {

	/** Accent par défaut du template (jaune signature « Laste »). */
	const DEFAULT_ACCENT = '#FFE21E';

	/**
	 * Base sombre neutre de la coque (`--cd-ink`) : pastille de langue active,
	 * pagination active. Elle NE dépend pas de l'accent, elle sert de socle.
	 */
	const NEUTRAL_DARK = '#181818';

	/**
	 * Part de neutre dans la sidebar dérivée (ROADMAP Phase 15, étape 15.5).
	 *
	 * 0.85 = 15 % d'accent, 85 % de `NEUTRAL_DARK`. Calibré par balayage du cube
	 * RGB (cf. docs/ICONES-ET-CHARTE-GESTION.md §4.3) : c'est le meilleur compromis
	 * entre une teinte réellement perceptible et la marge de contraste du texte de
	 * navigation. Plus bas, le fond s'éclaircit et le texte se rapproche du seuil ;
	 * plus haut, la teinte de marque disparaît et la dérivation ne sert plus à rien.
	 */
	const SIDE_MIX = 0.85;

	/**
	 * Luminance maximale de la sidebar dérivée.
	 *
	 * 🚨 Sans ce plafond, la dérivation est uniforme dans sa FORMULE mais pas dans
	 * son RÉSULTAT : un mélange à taux fixe transmet la clarté de l'accent autant
	 * que sa teinte. Mesuré — la sidebar sort à 1,0× la luminance du neutre pour un
	 * accent presque noir, 2,4× pour un vert moyen… et **4,0×** pour le jaune par
	 * défaut du template, qui vire au kaki nettement plus clair que le noir voulu.
	 *
	 * Le plafond rend la règle uniforme dans son résultat : « la sidebar est
	 * toujours à peu près aussi sombre, teintée de VOTRE couleur ». Il ne se
	 * déclenche que sur les accents très clairs — tous les autres ressortent
	 * intacts, y compris le vert de référence de la documentation (0.0216).
	 */
	const SIDE_MAX_LUM = 0.022;

	/**
	 * Part de blanc dans les SURFACES TEINTÉES qui portent du texte (Phase 17).
	 *
	 * Survol de ligne, ligne sélectionnée, ligne de transporteur dépliée, note du
	 * gérant : des fonds qui doivent se teinter de l'accent du client sans jamais
	 * mettre en péril ce qui est écrit dessus.
	 *
	 * 🚨 L'encre contraignante n'est PAS `--cd-ink` (#181818, confortable partout)
	 * mais `--cd-muted` (#666666), qui vit dans ces cellules — prix barré, coût,
	 * variantes, numéro de suivi. Le pire cas est l'accent NOIR : le mélange vaut
	 * alors 255×taux sur les trois canaux, donc le fond le plus sombre possible.
	 * Mesuré contre #666666 :
	 *
	 *   0.86 (= `--cd-accent-soft`) → #dbdbdb → 4,15:1  ❌ sous AA
	 *   0.94 (retenu)               → #f0f0f0 → 5,04:1  ✅
	 *
	 * C'est la raison d'être de cette constante : `--cd-accent-soft` existait déjà,
	 * mais il est calibré pour porter `--cd-ink`, pas `--cd-muted`. Le réutiliser
	 * ici — ce que faisait la note du gérant — ouvrait une brèche AA silencieuse,
	 * invisible avec l'accent jaune par défaut du template.
	 *
	 * Pas de plancher de luminance ici, contrairement à SIDE_MAX_LUM : à 0.94 il ne
	 * se déclencherait jamais, et un garde-fou qu'on ne peut pas mettre au rouge ne
	 * prouve rien (DoD §C.2). C'est le test qui MESURE le pire cas et publie sa
	 * valeur.
	 */
	const WASH_MIX = 0.94;

	/** Amorçage. */
	public static function init() {
		add_action( 'customize_register', array( __CLASS__, 'register' ) );
	}

	/**
	 * Déclare la section + le contrôle couleur dans le Personnalisateur.
	 *
	 * @param WP_Customize_Manager $wp_customize Gestionnaire du Personnalisateur.
	 */
	public static function register( $wp_customize ) {
		$wp_customize->add_section(
			'aod_cd_branding',
			array(
				'title'       => __( 'Espace de gestion', 'aod-client-dashboard' ),
				'description' => __( 'Couleur d’accent de l’espace gérant (/gestion). Le logo et le nom viennent de « Identité du site ».', 'aod-client-dashboard' ),
				'priority'    => 45,
			)
		);

		$wp_customize->add_setting(
			'aod_cd_accent',
			array(
				'default'           => self::DEFAULT_ACCENT,
				'sanitize_callback' => 'sanitize_hex_color',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				'aod_cd_accent',
				array(
					'label'       => __( 'Couleur d’accent', 'aod-client-dashboard' ),
					'description' => __( 'Boutons, éléments actifs, points forts de l’interface de gestion.', 'aod-client-dashboard' ),
					'section'     => 'aod_cd_branding',
				)
			)
		);
	}

	/** Valeur d'accent courante, toujours un hex valide. */
	public static function accent() {
		$c = sanitize_hex_color( (string) get_theme_mod( 'aod_cd_accent', self::DEFAULT_ACCENT ) );
		return $c ? $c : self::DEFAULT_ACCENT;
	}

	/**
	 * Fond de la sidebar, DÉRIVÉ de l'accent (étape 15.5).
	 *
	 * 🚨 Pourquoi dériver au lieu d'écrire la couleur. Le fond noir-vert voulu par
	 * un client donné ne peut PAS être écrit dans le CSS : règle template n°1 —
	 * rien de spécifique à un client en dur. Chaque futur clone hériterait des
	 * couleurs de ce client-là. En dérivant, un seul réglage (l'accent) suffit et
	 * chaque clone obtient une sidebar teintée de SA couleur.
	 *
	 * Le mélange part de la base neutre existante, et non du noir pur : vers le
	 * noir, un accent saturé donne un fond quasi noir (#001108 mesuré) où la teinte
	 * ne se lit plus — on obtenait le coût de la dérivation sans son bénéfice.
	 *
	 * @param string|null $accent Accent à dériver. Par défaut celui du site — le
	 *                            paramètre existe pour que les tests puissent
	 *                            balayer le cube RGB à travers CE code plutôt
	 *                            qu'en recopiant la formule (une copie ne verrait
	 *                            ni le plafond, ni ce qu'on ajoutera demain).
	 * @return string Hex.
	 */
	public static function side( $accent = null ) {
		$accent = ( null === $accent ) ? self::accent() : $accent;
		$side   = self::mix( self::rgb( $accent ), self::rgb( self::NEUTRAL_DARK ), self::SIDE_MIX );

		// Rassombrissement par paliers jusqu'au plafond (cf. SIDE_MAX_LUM). Même
		// forme que readable_on() : assombrir vers le noir fait décroître la
		// luminance de façon monotone, donc le premier palier qui passe est aussi
		// celui qui préserve le mieux la teinte. Le compteur borne la boucle ; le
		// noir finit toujours par passer.
		for ( $i = 0; $i < 40 && self::luminance( $side ) > self::SIDE_MAX_LUM; $i++ ) {
			$side = self::mix( $side, 0, 0.03 );
		}

		return self::to_hex( $side );
	}

	/**
	 * Surface teintée portant du texte, DÉRIVÉE de l'accent (Phase 17).
	 *
	 * Même raison d'être que side() : le survol d'une ligne de tableau était écrit
	 * `#fdf6d8` et la ligne sélectionnée `#fffdf0` — deux jaunes hérités de l'accent
	 * PAR DÉFAUT du template. Un client au bordeaux gardait des lignes jaunes.
	 * C'était la dernière couleur de client réellement en dur (règle template n°1).
	 *
	 * Dérivé en PHP et non en `color-mix()` CSS, délibérément : la garantie « AA
	 * pour tout accent » est produite par un test qui balaie le cube RGB en
	 * appelant CE code. Une valeur calculée par le navigateur obligerait le test à
	 * recopier la formule — exactement ce que side() interdit en toutes lettres.
	 *
	 * @param string|null $accent Accent à dériver. Par défaut celui du site — le
	 *                            paramètre existe pour que les tests passent par le
	 *                            vrai code plutôt que par une copie de la formule.
	 * @return string Hex.
	 */
	public static function wash( $accent = null ) {
		$accent = ( null === $accent ) ? self::accent() : $accent;
		return self::to_hex( self::mix( self::rgb( $accent ), 255, self::WASH_MIX ) );
	}

	/**
	 * Bloc `:root{…}` à injecter en ligne : accent + variantes dérivées.
	 * Toutes les valeurs sortent de sanitize_hex_color / de notre propre
	 * formatage hexadécimal → sûres à imprimer sans échappement supplémentaire.
	 *
	 * @return string CSS.
	 */
	public static function css_vars() {
		$hex = self::accent();
		$rgb = self::rgb( $hex );
		$lum = self::luminance( $rgb );

		// Encre lisible sur l'accent : on retient, entre noir et blanc, celle qui
		// contraste le mieux (ratio WCAG) — robuste sur les tons moyens (verts…)
		// qu'un simple seuil de luminance classerait à tort.
		$ink = self::best_ink( $lum );

		// Variante « survol / renforcée » : on assombrit un accent clair, on
		// éclaircit un accent déjà foncé — pour garder un écart visible.
		$strong = ( $lum > 0.20 )
			? self::to_hex( self::mix( $rgb, 0, 0.12 ) )
			: self::to_hex( self::mix( $rgb, 255, 0.18 ) );

		// Teinte douce (fonds discrets, anneaux de focus légers). Calibrée pour
		// porter `--cd-ink` : ne PAS l'utiliser sous du `--cd-muted` — cf. wash().
		$soft = self::to_hex( self::mix( $rgb, 255, 0.86 ) );

		// Surface teintée portant du texte, y compris `--cd-muted`. Cf. wash().
		$wash = self::wash( $hex );

		// Fond de la sidebar, teinté de l'accent (étape 15.5). Cf. side().
		$side = self::side();

		// Accent posé EN TEXTE sur nos surfaces sombres. C'est le pendant de `$ink` —
		// et le piège inverse : l'accent par défaut est un jaune vif, donc lisible, ce
		// qui masque le problème. Un accent moyen y tombe sous AA (un bleu de marque
		// n'y donne que 4,2:1) et un accent foncé y serait carrément invisible. On
		// l'éclaircit vers le blanc juste assez pour atteindre AA : la teinte reste
		// reconnaissable, et un accent déjà clair n'est pas touché.
		//
		// 🚨 Ces surfaces sont maintenant DEUX, et pas la même selon l'endroit : la
		// sidebar est dérivée de l'accent, tandis que la pastille de langue active et
		// la pagination active restent sur le neutre. Une seule variable les sert
		// toutes : on la calibre donc sur la PLUS CLAIRE des deux, celle où l'accent
		// contraste le moins — passer là garantit de passer sur l'autre.
		//
		// Et non, la sidebar n'est pas toujours la plus claire : mesuré sur le cube
		// RGB, 72 accents sur 4 096 (1,8 %, tous très sombres) donnent une sidebar
		// dérivée PLUS sombre que le neutre. Choisir « la sidebar » en dur aurait
		// sous-calibré ces cas-là sans que rien ne le signale.
		$neutral  = self::rgb( self::NEUTRAL_DARK );
		$side_rgb = self::rgb( $side );
		$lightest = ( self::luminance( $side_rgb ) >= self::luminance( $neutral ) ) ? $side_rgb : $neutral;
		$on_dark  = self::readable_on( $rgb, $lightest );

		return ':root{'
			. '--cd-accent:' . $hex . ';'
			. '--cd-accent-rgb:' . implode( ',', array_map( 'intval', $rgb ) ) . ';'
			. '--cd-accent-strong:' . $strong . ';'
			. '--cd-accent-ink:' . $ink . ';'
			. '--cd-accent-soft:' . $soft . ';'
			. '--cd-accent-wash:' . $wash . ';'
			. '--cd-accent-on-dark:' . $on_dark . ';'
			. '--cd-side:' . $side . ';'
			. '}';
	}

	/* ----------------------------------------------------------------- *
	 * Petites fonctions couleur (sans dépendance)                        *
	 * ----------------------------------------------------------------- */

	/** #rrggbb (ou #rgb) → array(r,g,b) entiers 0..255. */
	protected static function rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/** array(r,g,b) → #rrggbb, valeurs bornées. */
	protected static function to_hex( $rgb ) {
		return sprintf(
			'#%02x%02x%02x',
			max( 0, min( 255, (int) round( $rgb[0] ) ) ),
			max( 0, min( 255, (int) round( $rgb[1] ) ) ),
			max( 0, min( 255, (int) round( $rgb[2] ) ) )
		);
	}

	/**
	 * Encre (noir vs blanc) qui contraste le mieux avec une luminance donnée.
	 * Ratio WCAG = (Lclair+0.05)/(Lfoncé+0.05) ; on garde l'encre au plus fort ratio.
	 *
	 * L'encre foncée est le NOIR PUR, et non notre `--cd-ink` (#181818) : le client
	 * choisit son accent librement, la garantie doit donc tenir pour TOUTE couleur
	 * du cube. Avec #181818, le meilleur des deux encres tombe à 4,21:1 dans le pire
	 * cas (#1878f0, un bleu de marque banal ; le rouge pur plafonne à 4,44:1) — sous
	 * le seuil AA de 4,5. Avec le noir pur, le plancher mathématique du « meilleur des
	 * deux » remonte à 4,58:1 : plus aucun accent ne peut produire un bouton illisible.
	 * (Cette encre ne sert QUE de couleur de texte posée sur un fond d'accent.)
	 *
	 * @param float $lum Luminance relative de l'accent (0..1).
	 * @return string Hex de l'encre.
	 */
	protected static function best_ink( $lum ) {
		$c_wht = ( 1.0 + 0.05 ) / ( $lum + 0.05 );
		$c_blk = ( $lum + 0.05 ) / 0.05;
		return ( $c_blk >= $c_wht ) ? '#000000' : '#ffffff';
	}

	/**
	 * Éclaircit une couleur vers le blanc jusqu'à ce qu'elle atteigne AA sur un
	 * fond SOMBRE donné. Le fond étant sombre, éclaircir augmente le contraste de
	 * façon monotone : le premier palier qui passe est aussi le plus fidèle à la
	 * teinte d'origine. Une couleur déjà lisible ressort inchangée.
	 *
	 * @param array $rgb Couleur à ajuster.
	 * @param array $bg  Fond sombre sur lequel elle sera posée.
	 * @return string Hex.
	 */
	protected static function readable_on( $rgb, $bg ) {
		$l_bg   = self::luminance( $bg );
		$target = 4.6; // Petite marge au-dessus du seuil AA (4.5).

		for ( $i = 0; $i <= 20; $i++ ) {
			$try   = self::mix( $rgb, 255, $i * 0.05 );
			$l     = self::luminance( array_map( 'round', $try ) );
			$ratio = ( max( $l, $l_bg ) + 0.05 ) / ( min( $l, $l_bg ) + 0.05 );
			if ( $ratio >= $target ) {
				return self::to_hex( $try );
			}
		}
		return '#ffffff'; // Inatteignable en pratique (le blanc passe toujours).
	}

	/** Luminance relative sRGB (0..1), pour choisir l'encre par contraste. */
	protected static function luminance( $rgb ) {
		$lin = array();
		foreach ( $rgb as $v ) {
			$v     = $v / 255;
			$lin[] = ( $v <= 0.03928 ) ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
	}

	/**
	 * Mélange linéaire de $rgb vers une cible, à hauteur de $amt (0..1).
	 *
	 * La cible accepte un SCALAIRE achromatique (0 = noir, 255 = blanc — la forme
	 * historique, utilisée par `$strong`, `$soft` et `readable_on()`) ou un
	 * array(r,g,b) : la sidebar dérivée mélange vers `NEUTRAL_DARK`, qui se trouve
	 * être gris mais n'a aucune raison de le rester si la coque évolue.
	 *
	 * @param array     $rgb    Couleur de départ.
	 * @param array|int $target Cible du mélange.
	 * @param float     $amt    Part de la cible (0 = inchangé, 1 = cible pure).
	 * @return array Composantes non arrondies.
	 */
	protected static function mix( $rgb, $target, $amt ) {
		$t   = is_array( $target ) ? $target : array( $target, $target, $target );
		$out = array();
		foreach ( array( 0, 1, 2 ) as $i ) {
			$out[ $i ] = $rgb[ $i ] + ( $t[ $i ] - $rgb[ $i ] ) * $amt;
		}
		return $out;
	}
}
