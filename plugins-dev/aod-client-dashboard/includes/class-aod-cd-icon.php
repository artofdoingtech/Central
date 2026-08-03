<?php
/**
 * Icônes vectorielles de /gestion — catalogue fermé (ROADMAP Phase 15, étape 15.2).
 *
 * Remplace les emojis Unicode, rendus par la police emoji du SYSTÈME : le
 * tableau de bord n'avait pas le même visage sur Windows (Segoe, très cartoon),
 * macOS (Apple Color Emoji, 3D brillant) et Android. Aucun contrôle sur le trait,
 * la couleur, l'alignement.
 *
 * 🚨 POURQUOI UN CATALOGUE FERMÉ, ET PAS UN CHAMP LIBRE.
 * `wp_kses_post()` SUPPRIME les `<svg>` (dette 14 de docs/ARCHITECTURE.md), et les
 * points d'injection historiques utilisaient `esc_html()` — parfait pour un emoji,
 * destructeur pour un SVG. On ne peut donc pas « échapper autrement » : il faut que
 * le HTML rendu ne vienne JAMAIS d'une saisie. D'où le même patron que
 * `AOD_COD_Landing::schema()` — une liste blanche en PHP, des fichiers du dépôt,
 * et rien d'autre. Un nom absent du catalogue ne rend RIEN.
 *
 * Les fichiers sont des SVG Phosphor (MIT) déposés tels quels dans
 * `assets/icons/{regular,fill}/` — cf. le README de ce dossier. Ils portent déjà
 * `fill="currentColor"` : c'est ce qui permet à l'accent du Personnalisateur de
 * piloter les icônes sans qu'aucune couleur ne soit écrite ici.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Icon {

	/** Graisse par défaut. `fill` est réservé à l'état actif de la navigation. */
	const DEFAULT_WEIGHT = 'regular';

	/** Taille par défaut, en px. */
	const DEFAULT_SIZE = 20;

	/**
	 * Contenu des fichiers déjà lus, par « graisse/nom ».
	 *
	 * La navigation rend 13 icônes et un onglet de stats en rend 7 : sans ce cache,
	 * autant d'accès disque par page, dont plusieurs fois le même fichier.
	 *
	 * @var array<string,string>
	 */
	protected static $cache = array();

	/**
	 * LE CATALOGUE — seuls ces noms existent.
	 *
	 * Clé   = nom logique utilisé dans le code (stable, indépendant du jeu d'icônes :
	 *         si Phosphor était un jour remplacé, seule la valeur changerait).
	 * Valeur = nom du fichier dans `assets/icons/<graisse>/`.
	 *
	 * @return array<string,string>
	 */
	public static function catalog() {
		return array(
			// -- Sections de /gestion (sidebar). Disponibles en `regular` ET `fill`.
			'home'       => 'house',
			'orders'     => 'receipt',
			'abandoned'  => 'shopping-cart-simple',
			'products'   => 'package',
			'stock'      => 'stack',
			'categories' => 'tag',
			'shipping'   => 'truck',
			'tracking'   => 'map-pin',
			'marketing'  => 'crosshair',
			'whatsapp'   => 'whatsapp-logo',
			'team'       => 'users',
			'account'    => 'user-circle',
			'storefront' => 'storefront',
			// Section masquée du menu (s'ouvre depuis un produit), mais routable :
			// elle a besoin d'une icône comme les autres.
			'landing'    => 'rocket-launch',

			// -- Indicateurs (KPI) et états. `regular` uniquement.
			'money'      => 'money',
			'trend-up'   => 'trend-up',
			'percent'    => 'percent',
			'cancel'     => 'x-circle',
			'check'      => 'check-circle',
			'transit'    => 'path',
			'returned'   => 'arrow-u-up-left',
			'warning'    => 'warning',
			'blocked'    => 'prohibit',
			'inbox'      => 'tray-arrow-down',
			'question'   => 'question',
			'pending'    => 'hourglass',
			'delay'      => 'timer',
			// Même glyphe que `abandoned`, nom différent — et c'est le but. Le KPI
			// « Panier moyen » n'a rien à voir avec les commandes abandonnées :
			// réutiliser le nom de la section aurait fait mentir le code au premier
			// lecteur, et piégé quiconque change l'icône de l'une des deux.
			'cart'       => 'shopping-cart-simple',

			// -- États vides et messages (Phase 15.7).
			'locked'     => 'lock',
			'unplugged'  => 'plug',
			'chart'      => 'chart-line',
			'search'     => 'magnifying-glass',
			'list'       => 'clipboard-text',
			'empty-tray' => 'tray',

			// -- Marques. Elles remplacent des SUBSTITUTS : la régie TikTok était
			// figurée par « ♪ » et Snapchat par « 👻 », faute de logo sous la main.
			// Phosphor les fournit, sous licence MIT.
			'tiktok'     => 'tiktok-logo',
			'snapchat'   => 'snapchat-logo',
			'meta'       => 'meta-logo',
			'google'     => 'google-logo',
			// Régie « Conversions » : pas une marque, une notion. Le substitut
			// précédent était un « $ » — dans une boutique en dinars.
			'conversion' => 'currency-circle-dollar',

			// -- Éditeur produit, panneaux, actions (Phase 15.7b).
			'note-edit'  => 'note-pencil',
			'note'       => 'note',
			'images'     => 'images',
			'palette'    => 'palette',
			'camera'     => 'camera',
			'calendar'   => 'calendar-blank',
			'close'      => 'x',
			'map'        => 'map-trifold',
			'download'   => 'arrow-line-down',
			'user'       => 'user',
			'phone'      => 'phone',
			'trash'      => 'trash',
			'refresh'    => 'arrows-clockwise',
			'edit'       => 'pencil-simple',
			'save'       => 'floppy-disk',
			'video'      => 'film-slate',
			'blocks'     => 'squares-four',
			'template'   => 'layout',
			// Distincte de `cancel` À DESSEIN : une rupture de stock et une commande
			// annulée sont deux faits différents, et les KPI Stock les affichent
			// côte à côte. Le même glyphe pour les deux les rendrait confondables.
			'rupture'    => 'warning-octagon',
		);
	}

	/**
	 * Noms disponibles en graisse `fill` (état actif de la navigation).
	 *
	 * L'état actif se joue sur la GRAISSE et non sur la couleur : c'est un signal
	 * qui tient quel que soit l'accent choisi par le client, là où une pastille
	 * colorée dépend entièrement de la teinte retenue.
	 *
	 * @return string[]
	 */
	public static function filled() {
		return array(
			'home', 'orders', 'abandoned', 'products', 'stock', 'categories',
			'shipping', 'tracking', 'marketing', 'whatsapp', 'team', 'account',
			'storefront', 'landing',
		);
	}

	/**
	 * Icônes à MIROITER en RTL (arabe).
	 *
	 * 🚨 Volontairement court, et ce n'est pas un oubli. Miroiter toute la famille
	 * est le réflexe qui casse le sens :
	 *   - `trend-up` est une courbe qui MONTE de gauche à droite. Miroitée, elle
	 *     descend : l'icône de « Marge estimée » dirait l'inverse de sa valeur.
	 *     Elle ne doit JAMAIS être retournée.
	 *   - `inbox` (flèche vers le bas), `check`, `cancel`, `warning`, `blocked`,
	 *     `map-pin`, `package`, `tag` sont symétriques ou verticaux : rien à faire.
	 * Seules les icônes qui portent un sens de LECTURE horizontal sont ici.
	 *
	 * @return string[]
	 */
	public static function directional() {
		return array(
			'shipping',  // le camion roule vers la droite
			'returned',  // flèche de retour, explicitement vers la gauche
		);
	}

	/**
	 * Rend une icône du catalogue.
	 *
	 * @param string $name Nom logique (clé du catalogue).
	 * @param array  $args {
	 *     @type string $weight regular|fill (défaut : regular).
	 *     @type int    $size   Côté en px (défaut : 20).
	 *     @type string $class  Classes CSS supplémentaires.
	 * }
	 * @return string HTML sûr — chaîne vide si le nom est inconnu.
	 */
	public static function svg( $name, $args = array() ) {
		$catalog = self::catalog();

		if ( ! isset( $catalog[ $name ] ) ) {
			// Un trou dans la navigation doit CRIER en développement, pas se fondre
			// dans le décor : une icône manquante passe inaperçue à l'œil.
			_doing_it_wrong(
				__METHOD__,
				esc_html( sprintf( 'Icône inconnue : « %s ». Ajoutez-la au catalogue.', $name ) ),
				'0.45.0'
			);
			return '';
		}

		$args   = wp_parse_args(
			$args,
			array(
				'weight' => self::DEFAULT_WEIGHT,
				'size'   => self::DEFAULT_SIZE,
				'class'  => '',
			)
		);
		$weight = ( 'fill' === $args['weight'] && in_array( $name, self::filled(), true ) ) ? 'fill' : 'regular';
		$size   = max( 8, (int) $args['size'] );

		$inner = self::inner( $weight, $catalog[ $name ] );
		if ( '' === $inner ) {
			return '';
		}

		$classes = 'aod-cd-i';
		if ( '' !== $args['class'] ) {
			$classes .= ' ' . $args['class'];
		}

		// Attributs reconstruits ici, jamais lus du fichier : la source reste
		// identique à l'amont (vérifiable), la présentation nous appartient.
		// `aria-hidden` : ces icônes sont DÉCORATIVES — chaque lien de nav et chaque
		// KPI porte déjà son libellé texte. Une icône annoncée en plus serait du
		// bruit au lecteur d'écran.
		return sprintf(
			'<svg class="%1$s"%2$s width="%3$d" height="%3$d" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true" focusable="false">%4$s</svg>',
			esc_attr( $classes ),
			in_array( $name, self::directional(), true ) ? ' data-dir="1"' : '',
			$size,
			$inner
		);
	}

	/**
	 * Rend une poignée d'icônes pour le JS, sous forme `nom => SVG`.
	 *
	 * On expose le SVG DÉJÀ RENDU, pas le nom : le JS n'a donc aucun moyen de
	 * fabriquer une icône hors catalogue, et il n'y a pas de second catalogue à
	 * tenir à jour côté client. Un nom inconnu est simplement absent de la carte —
	 * `svg()` a déjà crié à sa place.
	 *
	 * @param string[] $names Noms logiques nécessaires au JS.
	 * @param array    $args  Options passées à svg().
	 * @return array<string,string>
	 */
	public static function js_map( $names, $args = array() ) {
		$map = array();
		foreach ( (array) $names as $n ) {
			$svg = self::svg( $n, $args );
			if ( '' !== $svg ) {
				$map[ $n ] = $svg;
			}
		}
		return $map;
	}

	/**
	 * Contenu interne d'un fichier SVG (tout ce qui est entre `<svg>` et `</svg>`).
	 *
	 * @param string $weight regular|fill.
	 * @param string $file   Nom de fichier sans extension.
	 * @return string Chaîne vide si le fichier manque ou n'est pas exploitable.
	 */
	protected static function inner( $weight, $file ) {
		$key = $weight . '/' . $file;
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$path = AOD_CD_PATH . 'assets/icons/' . $key . '.svg';
		$raw  = is_readable( $path ) ? file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions -- fichier local du plugin, pas une URL.

		$inner = '';
		if ( $raw && preg_match( '#<svg[^>]*>(.*)</svg>#si', $raw, $m ) ) {
			$inner = trim( $m[1] );
		}

		self::$cache[ $key ] = $inner;
		return $inner;
	}
}
