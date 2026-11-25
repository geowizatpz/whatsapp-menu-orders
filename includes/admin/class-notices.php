<?php
/**
 * Admin notices for setup problems.
 *
 * @package WFO
 */

namespace WFO\Admin;

use WFO\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Small, dismissible setup notices.
 */
class Notices {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
	}

	/**
	 * Current screen belongs to the plugin.
	 *
	 * @return bool
	 */
	private static function is_plugin_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen ) {
			return false;
		}

		return false !== strpos( (string) $screen->id, 'wfo' ) || false !== strpos( (string) $screen->post_type, 'wfo' );
	}

	/**
	 * Print notices.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$number = (string) Settings::get( 'whatsapp_number' );

		if ( '' === $number && self::is_plugin_screen() ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'WhatsApp Menu & Orders:', 'whatsapp-menu-orders' ),
				esc_html__( 'Add the restaurant WhatsApp number so orders can be sent.', 'whatsapp-menu-orders' ),
				esc_url( admin_url( 'admin.php?page=wfo-settings' ) ),
				esc_html__( 'Open settings', 'whatsapp-menu-orders' )
			);
		}

		if ( self::is_plugin_screen() && ! did_action( 'elementor/loaded' ) ) {
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				esc_html__( 'Elementor is not active. The [wfo_menu] shortcode works on any page, and the Elementor widget appears once Elementor is activated.', 'whatsapp-menu-orders' )
			);
		}
	}
}
