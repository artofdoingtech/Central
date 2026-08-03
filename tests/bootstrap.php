<?php
/**
 * Harnais de tests unitaires — socle commun.
 *
 * Pourquoi pas PHPUnit ? Le template n'a ni Composer ni internet sortant garanti
 * (cf. TEMPLATE.md §3). Un clone doit pouvoir lancer `bash tests/run.sh` le jour
 * de son installation, sans rien télécharger. Le harnais tient donc en un fichier
 * et s'exécute dans WordPress via `wp eval-file` — donc avec les vraies classes,
 * les vrais `get_locale()`, `sanitize_key()`, etc. Ce ne sont pas des mocks.
 *
 * Un test = une assertion nommée. Sortie ≠ 0 si une seule échoue (utilisable en CI).
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Compteurs, assertions, et accès aux méthodes non publiques.
 */
class AOD_Test {

	/** @var int */
	public static $pass = 0;

	/** @var int */
	public static $fail = 0;

	/** @var string[] */
	public static $failures = array();

	/** @var string */
	protected static $group = '';

	/**
	 * Ouvre un groupe de tests (purement cosmétique, pour lire la sortie).
	 *
	 * @param string $name Titre du groupe.
	 */
	public static function group( $name ) {
		self::$group = $name;
		echo "\n\033[1m▸ {$name}\033[0m\n";
	}

	/**
	 * Assertion de base.
	 *
	 * @param bool   $cond Condition attendue vraie.
	 * @param string $msg  Ce que le test prouve (phrase complète).
	 * @param string $why  Détail affiché en cas d'échec.
	 */
	public static function ok( $cond, $msg, $why = '' ) {
		if ( $cond ) {
			++self::$pass;
			echo "  \033[32m✓\033[0m {$msg}\n";
			return;
		}
		++self::$fail;
		self::$failures[] = self::$group . ' — ' . $msg . ( '' !== $why ? " ({$why})" : '' );
		echo "  \033[31m✗ {$msg}\033[0m" . ( '' !== $why ? " → {$why}" : '' ) . "\n";
	}

	/**
	 * Égalité stricte, avec diff lisible en cas d'échec.
	 *
	 * @param mixed  $expected Valeur attendue.
	 * @param mixed  $actual   Valeur obtenue.
	 * @param string $msg      Ce que le test prouve.
	 */
	public static function eq( $expected, $actual, $msg ) {
		$same = ( $expected === $actual );
		self::ok(
			$same,
			$msg,
			$same ? '' : 'attendu ' . self::dump( $expected ) . ', obtenu ' . self::dump( $actual )
		);
	}

	/**
	 * Appelle une méthode `protected`/`private` statique. Ces méthodes portent des
	 * garanties (contraste AA, mapping de statuts) qu'on veut couvrir sans les
	 * rendre publiques juste pour les tests.
	 *
	 * @param string $class  Nom de classe.
	 * @param string $method Nom de méthode.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	public static function call_static( $class, $method, $args = array() ) {
		$m = new ReflectionMethod( $class, $method );
		$m->setAccessible( true );
		return $m->invokeArgs( null, $args );
	}

	/**
	 * Appelle une méthode `protected` d'instance.
	 *
	 * @param object $obj    Instance.
	 * @param string $method Nom de méthode.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	public static function call( $obj, $method, $args = array() ) {
		$m = new ReflectionMethod( get_class( $obj ), $method );
		$m->setAccessible( true );
		return $m->invokeArgs( $obj, $args );
	}

	/**
	 * Exécute un test avec le site basculé en arabe, puis restaure.
	 *
	 * `AOD_COD_Data::is_arabic()` lit `get_locale()` : on filtre la locale plutôt
	 * que de toucher aux options (le test ne doit rien laisser derrière lui).
	 *
	 * @param callable $fn Test à exécuter en contexte arabe.
	 * @return mixed
	 */
	public static function in_arabic( $fn ) {
		$to_ar = static function () {
			return 'ar';
		};
		add_filter( 'locale', $to_ar, 99 );
		try {
			return $fn();
		} finally {
			remove_filter( 'locale', $to_ar, 99 );
		}
	}

	/**
	 * Représentation courte d'une valeur pour les messages d'échec.
	 *
	 * @param mixed $v Valeur.
	 * @return string
	 */
	protected static function dump( $v ) {
		if ( is_bool( $v ) ) {
			return $v ? 'true' : 'false';
		}
		if ( is_null( $v ) ) {
			return 'null';
		}
		if ( is_scalar( $v ) ) {
			return is_string( $v ) ? "'{$v}'" : (string) $v;
		}
		return str_replace( array( "\n", '  ' ), '', var_export( $v, true ) );
	}

	/**
	 * Récapitulatif final. Sort en erreur si un test a échoué.
	 */
	public static function summary() {
		$total = self::$pass + self::$fail;
		echo "\n" . str_repeat( '─', 60 ) . "\n";
		if ( self::$fail ) {
			echo "\033[31m✗ " . self::$fail . " échec(s)\033[0m sur {$total} tests :\n";
			foreach ( self::$failures as $f ) {
				echo "    • {$f}\n";
			}
			echo "\n";
			exit( 1 );
		}
		echo "\033[32m✓ {$total} tests passés.\033[0m\n\n";
		exit( 0 );
	}
}
