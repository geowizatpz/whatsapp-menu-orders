<?php
/**
 * Shortcode fallback for non-Elementor pages.
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * [wfo_menu] shortcode.
 */
class Shortcodes {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_shortcode( 'wfo_menu', array( __CLASS__, 'menu' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function menu( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'categories'       => '',
				'columns'          => '3',
				'layout'           => 'grid',
				'show_images'      => 'yes',
				'show_description' => 'yes',
				'show_catbar'      => 'yes',
				'show_search'      => 'yes',
				'sticky_cart'      => 'yes',
				'limit'            => '-1',
			),
			$atts,
			'wfo_menu'
		);

		$categories = array_filter( array_map( 'absint', preg_split( '/[,\s]+/', (string) $atts['categories'] ) ) );

		return Render::menu(
			array(
				'categories'       => $categories,
				'columns'          => (int) $atts['columns'],
				'layout'           => $atts['layout'],
				'show_images'      => in_array( $atts['show_images'], array( 'yes', '1', 'true' ), true ),
				'show_description' => in_array( $atts['show_description'], array( 'yes', '1', 'true' ), true ),
				'show_catbar'      => in_array( $atts['show_catbar'], array( 'yes', '1', 'true' ), true ),
				'show_search'      => in_array( $atts['show_search'], array( 'yes', '1', 'true' ), true ),
				'sticky_cart'      => in_array( $atts['sticky_cart'], array( 'yes', '1', 'true' ), true ),
				'limit'            => (int) $atts['limit'],
			)
		);
	}
}
