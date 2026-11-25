<?php
/**
 * Class autoloader.
 *
 * Rule: WFO\Admin\Menu_Meta -> includes/admin/class-menu-meta.php
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * PSR-4-ish loader following the plugin file naming convention.
 */
class Autoloader {

	/**
	 * Register the loader.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load a class file when it is first used.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function load( $class ) {
		if ( 0 !== strpos( $class, 'WFO\\' ) ) {
			return;
		}

		$relative = substr( $class, 4 );
		$relative = strtolower( str_replace( '_', '-', $relative ) );
		$parts    = explode( '\\', $relative );
		$file     = array_pop( $parts );
		$path     = WFO_DIR . 'includes/';

		if ( $parts ) {
			$path .= implode( '/', $parts ) . '/';
		}

		$path .= 'class-' . $file . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
