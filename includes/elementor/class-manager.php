<?php
/**
 * Elementor integration bootstrap.
 *
 * @package WFO
 */

namespace WFO\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Food Ordering" widget category and the plugin widgets.
 */
class Manager {

	/**
	 * Elementor widget category slug.
	 *
	 * @var string
	 */
	const CATEGORY = 'wfo';

	/**
	 * Register hooks with Elementor.
	 */
	public static function init() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		$elementor = \Elementor\Plugin::$instance;

		if ( ! $elementor ) {
			return;
		}

		/*
		 * The plugin boots this class on `elementor/init`, which Elementor fires at the
		 * end of its own init() after building the managers. Registering a widget through
		 * `elementor/widgets/register` only works if that hook has not fired yet, and the
		 * widgets manager fires it from its constructor. Use the documented hook when it
		 * is still ahead of us and register directly when it already ran.
		 */
		if ( did_action( 'elementor/widgets/register' ) ) {
			self::register_widgets( $elementor->widgets_manager );
		} else {
			add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
		}

		// Same for the category: the elements manager fires its hook lazily.
		if ( did_action( 'elementor/elements/categories_registered' ) ) {
			self::register_category( $elementor->elements_manager );
		} else {
			add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
		}
	}

	/**
	 * Add the "Food Ordering" category to the Elementor panel.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public static function register_category( $elements_manager ) {
		if ( ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) {
			return;
		}

		$elements_manager->add_category(
			self::CATEGORY,
			array(
				'title' => esc_html__( 'Food Ordering', 'whatsapp-menu-orders' ),
				'icon'  => 'eicon-menu-bar',
			)
		);
	}

	/**
	 * Register the plugin widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public static function register_widgets( $widgets_manager ) {
		if ( ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'register' ) ) {
			return;
		}

		if ( ! class_exists( __NAMESPACE__ . '\Widget_Menu' ) ) {
			return;
		}

		$widgets_manager->register( new Widget_Menu() );
	}
}
