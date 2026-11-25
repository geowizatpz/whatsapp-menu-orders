<?php
/**
 * Plugin bootstrap.
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin modules together.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot hooks. Called on plugins_loaded.
	 */
	public function boot() {
		I18n::init();
		Settings::init();
		Capabilities::init();
		Post_Types::init();
		Assets::init();
		Shortcodes::init();
		Rest::init();

		if ( is_admin() ) {
			Admin\Menu_Meta::init();
			Admin\Category_Meta::init();
			Admin\Orders_Admin::init();
			Admin\Settings_Page::init();
			Admin\Notices::init();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			CLI::init();
		}

		// The block editor gets in the way of the price / extras meta box, so menu items use the classic editor.
		add_filter( 'use_block_editor_for_post_type', array( $this, 'classic_editor_for_items' ), 10, 2 );

		add_action( 'elementor/init', array( $this, 'elementor' ) );
		add_action( 'before_delete_post', array( $this, 'on_delete_post' ) );
	}

	/**
	 * Menu items are simpler to edit with the classic editor.
	 *
	 * @param bool   $use       Whether to use the block editor.
	 * @param string $post_type Post type being edited.
	 * @return bool
	 */
	public function classic_editor_for_items( $use, $post_type ) {
		return in_array( $post_type, array( WFO_CPT_ITEM, WFO_CPT_ORDER ), true ) ? false : $use;
	}

	/**
	 * Register Elementor integration once Elementor is ready.
	 */
	public function elementor() {
		Elementor\Manager::init();
	}

	/**
	 * Clean up order meta when an order is deleted permanently.
	 *
	 * @param int $post_id Post id.
	 */
	public function on_delete_post( $post_id ) {
		if ( WFO_CPT_ORDER !== get_post_type( $post_id ) ) {
			return;
		}

		do_action( 'wfo_order_deleted', $post_id );
	}
}
