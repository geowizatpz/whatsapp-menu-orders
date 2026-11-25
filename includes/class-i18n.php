<?php
/**
 * Translation loading and the optional front-end language override.
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * Small i18n helper.
 */
class I18n {

	/**
	 * Effective locale for the current request.
	 *
	 * @var string|null
	 */
	private static $locale = null;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'load_textdomain' ), 1 );
		add_filter( 'language_attributes', array( __CLASS__, 'language_attributes' ) );
	}

	/**
	 * Make the document language match the menu language.
	 *
	 * @param string $output Existing language attributes.
	 * @return string
	 */
	public static function language_attributes( $output ) {
		if ( is_admin() ) {
			return $output;
		}

		$locale = str_replace( '_', '-', self::locale() );

		return (string) preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $locale ) . '"', $output );
	}

	/**
	 * Load the plugin translations, honouring the ui_language setting.
	 */
	public static function load_textdomain() {
		$locale = self::locale();
		$file   = WFO_DIR . 'languages/' . WFO_TD . '-' . $locale . '.mo';

		if ( is_readable( $file ) ) {
			load_textdomain( WFO_TD, $file );
			return;
		}

		load_plugin_textdomain( WFO_TD, false, dirname( plugin_basename( WFO_FILE ) ) . '/languages' );
	}

	/**
	 * Locale used for plugin strings.
	 *
	 * The front end can be forced to English or Spanish regardless of the site locale,
	 * which is what makes a single bilingual menu page possible.
	 *
	 * @return string
	 */
	public static function locale() {
		if ( null !== self::$locale ) {
			return self::$locale;
		}

		$site   = get_locale();
		$chosen = Settings::get( 'ui_language' );
		$locale = $site;

		if ( self::is_front_end_request() && in_array( $chosen, array( 'en_US', 'es_ES' ), true ) ) {
			$locale = $chosen;
		}

		if ( 0 === strpos( $locale, 'es' ) && ! is_readable( WFO_DIR . 'languages/' . WFO_TD . '-' . $locale . '.mo' ) ) {
			$locale = 'es_ES';
		}

		self::$locale = $locale;

		return self::$locale;
	}

	/**
	 * Force the configured menu language for the rest of the request.
	 *
	 * The Elementor editor renders the widget through AJAX, where is_admin()
	 * is true and the front-end locale was never applied. Calling this from the
	 * render path guarantees the preview matches the published page.
	 */
	public static function ensure_menu_language() {
		$chosen = Settings::get( 'ui_language' );

		if ( ! in_array( $chosen, array( 'en_US', 'es_ES' ), true ) ) {
			return;
		}

		$file = WFO_DIR . 'languages/' . WFO_TD . '-' . $chosen . '.mo';

		if ( is_readable( $file ) ) {
			load_textdomain( WFO_TD, $file );
			self::$locale = $chosen;
		}
	}

	/**
	 * True for requests that render the public menu.
	 *
	 * The Elementor editor renders the widget from wp-admin and from AJAX, so
	 * is_admin() alone would show the restaurant an English preview of a
	 * Spanish menu.
	 *
	 * @return bool
	 */
	private static function is_front_end_request() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		if ( ! is_admin() ) {
			return true;
		}

		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing flag.
			return true;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		return wp_doing_ajax() && '' !== $action && 0 === strpos( $action, 'elementor' );
	}

	/**
	 * Is the effective language Spanish?
	 *
	 * @return bool
	 */
	public static function is_spanish() {
		return 0 === strpos( self::locale(), 'es' );
	}

	/**
	 * Reset the cached locale (tests, language switchers).
	 */
	public static function reset() {
		self::$locale = null;
	}
}
