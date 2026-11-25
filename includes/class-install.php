<?php
/**
 * Activation, deactivation and first-run setup.
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * Lifecycle handlers.
 */
class Install {

	/**
	 * Run on activation.
	 */
	public static function activate() {
		if ( false === get_option( WFO_OPTION ) ) {
			add_option( WFO_OPTION, Settings::sanitize( Settings::defaults() ) );
		}

		Post_Types::register();
		Capabilities::grant();
		flush_rewrite_rules();

		self::maybe_create_page();
	}

	/**
	 * Run on deactivation.
	 */
	public static function deactivate() {
		Capabilities::revoke();
		flush_rewrite_rules();
	}

	/**
	 * Create a ready-to-use ordering page the first time the plugin is activated.
	 */
	public static function maybe_create_page() {
		$settings = Settings::all();
		$page_id  = (int) $settings['menu_page_id'];

		if ( $page_id && get_post( $page_id ) ) {
			return;
		}

		$existing = get_page_by_path( 'order-online' );

		if ( $existing instanceof \WP_Post ) {
			Settings::update( array( 'menu_page_id' => (int) $existing->ID ) );
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Order Online', 'whatsapp-menu-orders' ),
				'post_name'    => 'order-online',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '<!-- wp:shortcode -->[wfo_menu]<!-- /wp:shortcode -->',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			Settings::update( array( 'menu_page_id' => (int) $page_id ) );
		}
	}
}
