<?php
/**
 * Markup rendering entry points.
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the menu app and its partials.
 */
class Render {

	/**
	 * Instance counter for multiple widgets per page.
	 *
	 * @var int
	 */
	private static $count = 0;

	/**
	 * Default render args.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'categories'       => array(),
			'layout'           => 'grid',
			'columns'          => 3,
			'show_images'      => true,
			'show_description' => true,
			'show_catbar'      => true,
			'show_search'      => true,
			'sticky_cart'      => true,
			'limit'            => -1,
			'instance'         => '',
			'class'            => '',
		);
	}

	/**
	 * Render the whole ordering app.
	 *
	 * @param array $args Render args.
	 * @return string
	 */
	public static function menu( $args = array() ) {
		I18n::ensure_menu_language();

		$args = wp_parse_args( $args, self::defaults() );

		if ( '' === $args['instance'] ) {
			++self::$count;
			$args['instance'] = 'wfo-' . self::$count;
		}

		$args['categories'] = array_filter( array_map( 'absint', (array) $args['categories'] ) );
		$args['columns']    = max( 1, min( 4, (int) $args['columns'] ) );
		$args['layout']     = 'list' === $args['layout'] ? 'list' : 'grid';

		$data = Menu_Repository::payload(
			array(
				'categories'  => $args['categories'],
				'limit'       => (int) $args['limit'],
				'instance'    => $args['instance'],
				'show_search' => (bool) $args['show_search'],
			)
		);

		$data['args'] = $args;

		Assets::enqueue();

		if ( empty( $data['items'] ) ) {
			return '<div class="wfo wfo--empty"><p>' . esc_html__( 'The menu is being prepared. Please check back soon.', 'whatsapp-menu-orders' ) . '</p></div>';
		}

		return self::template( 'menu', $data, $args );
	}

	/**
	 * Render one template file.
	 *
	 * @param string $name Template name without extension.
	 * @param array  $data Payload.
	 * @param array  $args Render args.
	 * @return string
	 */
	public static function template( $name, $data, $args ) {
		$name = preg_replace( '/[^a-z\-]/', '', (string) $name );
		$file = WFO_DIR . 'templates/' . $name . '.php';

		if ( ! is_readable( $file ) ) {
			return '';
		}

		ob_start();
		include $file;

		return (string) ob_get_clean();
	}

	/**
	 * JSON payload for the JS app.
	 *
	 * @param array $data Payload.
	 * @return string
	 */
	public static function json( $data ) {
		$json = wp_json_encode(
			$data,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);

		return false === $json ? '{}' : $json;
	}

	/**
	 * Format an amount for the current settings.
	 *
	 * @param int $minor Amount in minor units.
	 * @return string
	 */
	public static function money( $minor ) {
		return Money::format( (int) $minor );
	}
}
