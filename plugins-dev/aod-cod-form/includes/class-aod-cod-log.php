<?php
/**
 * Journal des sorties externes — avec rédaction des secrets (Phase 31.3).
 *
 * 🚨 CE QUI ÉTAIT FAUX. Le dépôt ne comptait **aucune** ligne de journal : 0
 * `error_log`, 0 `wc_get_logger` sur 24 513 lignes, pour SIX intégrations HTTP
 * externes (29 transporteurs, 3 régies publicitaires, WhatsApp, Turnstile)
 * **toutes en fail-open documenté**. Fail-open veut dire : quand l'appel échoue,
 * on continue sans bruit. Cumulé au silence total, cela donne un système qui ne
 * peut pas rapporter ses propres pannes.
 *
 * Ce n'est pas une gêne de confort. Le défaut TikTok de la Phase 13 — « le pixel
 * paraissait actif et zéro achat serveur n'arrivait » — a vécu exactement de ce
 * silence. Et un incident se produit chez un CLIENT, sur un hébergement où
 * personne n'a de console.
 *
 * ⚠️ CE QUE CETTE CLASSE N'EST PAS. Ce n'est pas de la télémétrie et ça ne
 * remplace pas les signaux déjà visibles par le gérant (l'alerte Turnstile de la
 * Phase 26, les notes de commande). C'est la trace technique qu'on demandera
 * quand le gérant dira « ça ne marche pas ».
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * 🔒 LA RÈGLE QUI PRIME SUR TOUT LE RESTE : AUCUN SECRET DANS LES JOURNAUX
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * L'audit de sécurité l'a relevé comme un ACQUIS : aujourd'hui le dépôt ne
 * journalise aucun secret — parce qu'il ne journalise rien. Ajouter des journaux
 * sans précaution, c'est échanger un défaut de diagnostic contre une fuite
 * d'identifiants. `wc-logs/` est un dossier de l'installation, lisible par tout
 * plugin, souvent embarqué dans une sauvegarde, parfois servi par le serveur web.
 *
 * 🚨 LE PIÈGE QUI REND CE PROBLÈME NON TRIVIAL. Le réflexe — masquer les clés
 * d'un tableau dont le nom ressemble à un secret — **ne suffit pas ici**, parce
 * que quatre de nos intégrations mettent leur secret DANS L'URL :
 *
 *   - Noest       : `?api_token=…&user_guid=…`  (et l'URL du bordereau, persistée
 *                   en meta de commande `_aod_ship_label`)
 *   - EcoTrack    : `?api_token=…`              (+ en-tête Bearer, les deux)
 *   - WhatsApp    : `?apikey=…`                 (CallMeBot)
 *   - Meta CAPI   : `?access_token=…`           (test de connexion)
 *
 * Une URL est UNE CHAÎNE, pas un tableau : un masqueur par nom de clé n'y voit
 * rien. Loguer « l'URL appelée » sur un échec — le réflexe le plus naturel du
 * monde — publierait donc le jeton du transporteur en clair.
 *
 * D'où TROIS couches, chacune aveugle là où une autre voit :
 *
 *   1. `redact()` par NOM DE CLÉ, récursivement — couvre les tableaux
 *      (corps de requête, en-têtes HTTP, réglages).
 *   2. par NOM DE PARAMÈTRE D'URL — couvre `?api_token=…` quelle que soit la
 *      valeur, y compris un secret jamais enregistré (saisi puis testé).
 *   3. par VALEUR CONNUE — remplace toute occurrence des secrets réellement
 *      configurés, où qu'elle apparaisse : dans un corps de réponse renvoyé en
 *      écho par l'API, dans un message d'exception, dans une URL exotique.
 *
 * Aucune ne suffit seule ; le cliquet `tests/unit/test-journalisation.php`
 * éprouve les trois séparément, et vérifie qu'elles ne masquent PAS ce qui doit
 * rester lisible (une URL masquée en entier ne diagnostique plus rien).
 *
 * ⚠️ NIVEAUX. `error` pour un échec, `debug` pour le détail. **Jamais `info` par
 * défaut** : un journal qui grossit sans raison est un journal que l'hébergeur
 * finit par désactiver, et on se retrouve au point de départ.
 *
 * ⚠️ LES LIGNES DE JOURNAL NE PASSENT PAS PAR `gettext`, ET C'EST VOULU. Ça
 * ressemble à une entorse à la règle 4 de CLAUDE.md (« toute chaîne visible doit
 * être traduisible ») ; ça n'en est pas une. Ces lignes ne sont pas de l'UI : le
 * gérant ne les lit jamais, elles s'adressent à qui dépanne l'installation. Les
 * faire passer par `__()` les rendrait dépendantes de la langue du site — le même
 * incident donnerait un journal en arabe chez un client et en français chez un
 * autre, ce qui rend le dépannage plus dur, pas plus accessible. Ce qui est
 * VISIBLE de ces échecs — les notes de commande, les messages d'écran — reste
 * traduit, lui.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Log {

	/** Source WooCommerce : détermine le nom du fichier dans WooCommerce → État → Journaux. */
	const SOURCE = 'aod-cod';

	/** Ce qui remplace un secret. Volontairement explicite : un champ vide se lirait comme une absence. */
	const MASK = '[secret masqué]';

	/**
	 * Noms de clés (et de paramètres d'URL) considérés comme portant un secret.
	 *
	 * Couvre les 5 noms réellement utilisés par les 29 transporteurs — `api_id`,
	 * `api_token`, `user_guid`, `token`, `key` — plus `secret` (Turnstile),
	 * `apikey` (CallMeBot) et les `*_capi_token` des trois régies.
	 *
	 * ⚠️ `user_guid` EST un secret, malgré son nom d'identifiant : Noest exige le
	 * couple `api_token` + `user_guid` pour authentifier (`is_configured()`), donc
	 * il fait partie des identifiants et se masque comme tel.
	 *
	 * ⚠️ Ce qui n'est DÉLIBÉRÉMENT pas là, parce que le masquer rendrait les
	 * journaux inutiles sans rien protéger : `domain` (EcoTrack — un nom d'hôte,
	 * et il est imposé en dur pour les 23 white-labels), `site` (la clé de SITE
	 * Turnstile, publique par construction, imprimée dans `data-sitekey`),
	 * `meta_test_event_code` (documenté « non secret » dans le code des pixels) et
	 * les identifiants de pixel, exposés de toute façon dans le JS de la page.
	 */
	const SECRET_KEY_PATTERN = '/(token|secret|apikey|api_?key|api_?id|user_guid|password|passwd|authorization|signature)/i';

	/**
	 * Noms de clés courts et génériques, masqués sur correspondance EXACTE
	 * uniquement — les inclure dans le motif ci-dessus masquerait trop large.
	 *
	 * `key` est le nom du secret de Procolis / E-com Delivery. Tel quel dans le
	 * motif, il attraperait « monkey », « keyword »… et tout champ dont le nom
	 * contient ces trois lettres.
	 */
	const SECRET_KEY_EXACT = array( 'key', 'auth', 'pwd' );

	/**
	 * Longueur minimale pour qu'une valeur soit masquée PAR VALEUR (couche 3).
	 *
	 * ⚠️ POURQUOI UN SEUIL. `str_replace('', …)` ne fait rien, et remplacer une
	 * valeur de 2 caractères masquerait la moitié du journal — « 16 » est une
	 * wilaya autant qu'un fragment de jeton. Le seuil ne crée PAS de trou : un
	 * secret plus court que ça reste couvert par les couches 1 et 2, qui ne
	 * regardent pas la valeur mais le nom. Aucune API réelle n'émet de jeton de
	 * moins de 8 caractères.
	 */
	const SECRET_MIN_LEN = 8;

	/** @var array|null Mémoïsation des valeurs de secrets (29 `get_option()` sinon, à chaque ligne). */
	protected static $secrets_cache = null;

	/* --------------------------------------------------------------------- */
	/* API publique                                                          */
	/* --------------------------------------------------------------------- */

	/**
	 * Un appel externe a échoué, ou une opération n'a pas abouti.
	 *
	 * @param string $canal   D'où ça vient : 'carrier', 'capi', 'whatsapp', 'turnstile', 'commande'.
	 * @param string $message Phrase courte, en clair.
	 * @param array  $contexte Détails structurés — rédigés avant écriture.
	 * @return void
	 */
	public static function error( $canal, $message, $contexte = array() ) {
		self::write( 'error', $canal, $message, $contexte );
	}

	/**
	 * Détail de diagnostic. N'est écrit que si l'exploitant a monté le niveau du
	 * journal WooCommerce — donc gratuit en fonctionnement normal.
	 *
	 * @param string $canal
	 * @param string $message
	 * @param array  $contexte
	 * @return void
	 */
	public static function debug( $canal, $message, $contexte = array() ) {
		self::write( 'debug', $canal, $message, $contexte );
	}

	/**
	 * Écrit réellement la ligne.
	 *
	 * Dégradation propre : sans WooCommerce (activation partielle, désinstallation
	 * en cours), on ne journalise pas plutôt que de provoquer une erreur fatale
	 * dans le chemin d'achat. Un journal ne doit JAMAIS casser ce qu'il observe.
	 *
	 * @param string $niveau 'error' | 'debug'.
	 * @param string $canal
	 * @param string $message
	 * @param array  $contexte
	 * @return void
	 */
	protected static function write( $niveau, $canal, $message, $contexte = array() ) {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		$logger = wc_get_logger();
		if ( ! $logger || ! method_exists( $logger, 'log' ) ) {
			return;
		}

		$ligne = '[' . (string) $canal . '] ' . (string) $message;

		$contexte = (array) $contexte;
		if ( $contexte ) {
			// 🔒 LE point de passage obligé. Tout ce qui entre dans le journal
			// traverse `redact()` — y compris le message, qui peut porter le
			// corps d'une réponse d'API renvoyant le jeton en écho.
			$ligne .= ' | ' . wp_json_encode( self::redact( $contexte ) );
		}

		$logger->log( $niveau, self::redact_string( $ligne ), array( 'source' => self::SOURCE ) );
	}

	/* --------------------------------------------------------------------- */
	/* 🔒 Rédaction                                                          */
	/* --------------------------------------------------------------------- */

	/**
	 * Rend une valeur sûre à journaliser.
	 *
	 * Récursif : un corps de requête imbrique volontiers les identifiants sous
	 * plusieurs niveaux (`{ data: [ { … } ], access_token: … }`).
	 *
	 * @param mixed $valeur
	 * @param int   $profondeur Garde-fou anti-récursion.
	 * @return mixed Même forme, secrets remplacés par self::MASK.
	 */
	public static function redact( $valeur, $profondeur = 0 ) {
		if ( $profondeur > 10 ) {
			return '[trop profond]';
		}

		if ( is_object( $valeur ) ) {
			$valeur = get_object_vars( $valeur );
		}

		if ( is_array( $valeur ) ) {
			$sortie = array();
			foreach ( $valeur as $cle => $sous ) {
				// COUCHE 1 — par nom de clé. On masque sans regarder la valeur :
				// un champ nommé `api_token` n'a aucune raison d'être lisible,
				// même s'il est vide ou visiblement factice.
				if ( self::is_secret_key( $cle ) ) {
					$sortie[ $cle ] = self::MASK;
					continue;
				}
				$sortie[ $cle ] = self::redact( $sous, $profondeur + 1 );
			}
			return $sortie;
		}

		if ( is_string( $valeur ) ) {
			return self::redact_string( $valeur );
		}

		return $valeur;
	}

	/**
	 * Rédige une chaîne : couches 2 (paramètres d'URL) et 3 (valeurs connues).
	 *
	 * @param string $texte
	 * @return string
	 */
	public static function redact_string( $texte ) {
		$texte = (string) $texte;
		if ( '' === $texte ) {
			return $texte;
		}

		// COUCHE 2 — par nom de paramètre d'URL. Attrape `?api_token=xyz` et
		// `&apikey=xyz` SANS connaître la valeur : couvre donc un secret saisi
		// dans le formulaire et testé avant d'être enregistré, qu'aucune lecture
		// d'option ne pourrait connaître.
		$texte = preg_replace_callback(
			'/([?&;]|^)([a-z0-9_\-\.\[\]]+)=([^&\s"\']*)/i',
			function ( $m ) {
				return self::is_secret_key( $m[2] ) ? $m[1] . $m[2] . '=' . self::MASK : $m[0];
			},
			$texte
		);

		// COUCHE 3 — par valeur connue. Dernier filet : un secret qui apparaît là
		// où on ne l'attendait pas — en écho dans un corps de réponse, dans le
		// message d'une exception, dans un en-tête recopié.
		foreach ( self::secrets() as $secret ) {
			$texte = str_replace( $secret, self::MASK, $texte );
		}

		return $texte;
	}

	/**
	 * Ce nom de clé désigne-t-il un secret ?
	 *
	 * @param mixed $cle
	 * @return bool
	 */
	protected static function is_secret_key( $cle ) {
		if ( ! is_string( $cle ) ) {
			return false; // Index numérique d'une liste.
		}
		$normalisee = strtolower( trim( $cle ) );
		if ( in_array( $normalisee, self::SECRET_KEY_EXACT, true ) ) {
			return true;
		}
		return (bool) preg_match( self::SECRET_KEY_PATTERN, $normalisee );
	}

	/**
	 * Valeurs de tous les secrets réellement configurés sur ce site.
	 *
	 * ⚠️ COLLECTE DYNAMIQUE, PAS UNE LISTE EN DUR. Les transporteurs sont
	 * énumérés via le registre (`AOD_Shipping::carriers()`, 29 aujourd'hui) et
	 * leurs réglages balayés par NOM DE CLÉ. Un transporteur ajouté demain, ou une
	 * clé de secret ajoutée à un transporteur existant, est donc couvert sans que
	 * personne ait à penser à revenir ici. Une liste en dur aurait été un piège à
	 * retardement : elle aurait vieilli en silence, et son vieillissement est
	 * précisément une fuite.
	 *
	 * Chaque secret est aussi enregistré sous ses formes ENCODÉES : la valeur part
	 * dans une URL via `add_query_arg()`, qui l'encode — le jeton dans l'URL n'est
	 * donc pas littéralement le jeton de l'option dès qu'il contient un caractère
	 * spécial.
	 *
	 * @return string[] Aiguilles à remplacer, dédoublonnées, les plus longues d'abord.
	 */
	public static function secrets() {
		if ( null !== self::$secrets_cache ) {
			return self::$secrets_cache;
		}

		$brutes = array();

		// Les trois options à secrets du plugin. On balaie leur CONTENU par nom de
		// clé plutôt que de nommer les champs un par un — même raison que pour les
		// transporteurs.
		foreach ( array( 'aod_cod_turnstile', 'aod_cod_pixels', 'aod_cod_whatsapp' ) as $option ) {
			$brutes = array_merge( $brutes, self::harvest( get_option( $option, array() ) ) );
		}

		// Les 29 transporteurs, via le registre.
		if ( class_exists( 'AOD_Shipping' ) ) {
			$shipping = AOD_Shipping::instance();
			if ( $shipping && method_exists( $shipping, 'carriers' ) ) {
				foreach ( (array) $shipping->carriers() as $carrier ) {
					if ( is_object( $carrier ) && method_exists( $carrier, 'settings' ) ) {
						$brutes = array_merge( $brutes, self::harvest( $carrier->settings() ) );
					}
				}
			}
		}

		// Le secret Turnstile peut venir d'une constante de wp-config, qui prime
		// sur l'option : sans ça, une installation « propre » (secret hors base)
		// serait la seule à fuiter.
		if ( defined( 'AOD_COD_TURNSTILE_SECRET_KEY' ) ) {
			$brutes[] = (string) AOD_COD_TURNSTILE_SECRET_KEY;
		}

		/**
		 * Secrets supplémentaires à ne jamais journaliser.
		 *
		 * Sert aussi de couture de test : le cliquet injecte ses témoins par ce
		 * filtre au lieu d'écrire dans les options du site.
		 *
		 * @param string[] $brutes
		 */
		$brutes = (array) apply_filters( 'aod_cod_log_secrets', $brutes );

		$aiguilles = array();
		foreach ( $brutes as $secret ) {
			if ( ! is_scalar( $secret ) ) {
				continue;
			}
			$secret = (string) $secret;
			if ( strlen( $secret ) < self::SECRET_MIN_LEN ) {
				continue;
			}
			$aiguilles[] = $secret;
			$aiguilles[] = rawurlencode( $secret );
			$aiguilles[] = urlencode( $secret );
		}

		$aiguilles = array_values( array_unique( array_filter( $aiguilles, function ( $s ) {
			return strlen( $s ) >= self::SECRET_MIN_LEN;
		} ) ) );

		// Les plus longues d'abord : si un secret contient l'autre, remplacer le
		// court en premier laisserait le reste du long en clair.
		usort( $aiguilles, function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		} );

		self::$secrets_cache = $aiguilles;
		return self::$secrets_cache;
	}

	/**
	 * Extrait d'un tableau de réglages les valeurs dont la CLÉ désigne un secret.
	 *
	 * @param mixed $reglages
	 * @param int   $profondeur
	 * @return string[]
	 */
	protected static function harvest( $reglages, $profondeur = 0 ) {
		if ( ! is_array( $reglages ) || $profondeur > 5 ) {
			return array();
		}
		$trouves = array();
		foreach ( $reglages as $cle => $valeur ) {
			if ( is_array( $valeur ) ) {
				$trouves = array_merge( $trouves, self::harvest( $valeur, $profondeur + 1 ) );
				continue;
			}
			if ( is_scalar( $valeur ) && self::is_secret_key( $cle ) ) {
				$trouves[] = (string) $valeur;
			}
		}
		return $trouves;
	}

	/**
	 * Oublie les secrets mémoïsés. Appelé après un enregistrement de réglages —
	 * sinon un jeton fraîchement changé ne serait pas masqué du reste de la
	 * requête, qui est exactement celle où l'on teste la connexion.
	 *
	 * @return void
	 */
	public static function flush_secrets() {
		self::$secrets_cache = null;
	}

	/**
	 * Branche l'invalidation du cache sur l'enregistrement des réglages.
	 *
	 * 🚨 CE N'EST PAS UNE OPTIMISATION, C'EST UNE CORRECTION DE FUITE. Le cas
	 * n'est pas théorique : dans l'écran WhatsApp de l'admin, `maybe_save()` et
	 * `send_test()` tournent tous deux sur `admin_init`, donc DANS LA MÊME
	 * REQUÊTE. Sans cette invalidation, le cache retiendrait l'ANCIENNE clé, et la
	 * clé qu'on vient d'enregistrer partirait en clair dans le journal — sur le
	 * test qui vient précisément de l'utiliser. Le scénario le plus probable est
	 * donc aussi celui qui fuirait.
	 *
	 * @return void
	 */
	public static function boot() {
		$oublier = function ( $option ) {
			if ( is_string( $option ) && 0 === strpos( $option, 'aod_' ) ) {
				self::flush_secrets();
			}
		};
		add_action( 'updated_option', $oublier, 10, 1 );
		add_action( 'added_option', $oublier, 10, 1 );
	}
}
