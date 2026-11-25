<?php
/**
 * Elementor widget: food menu and WhatsApp ordering.
 *
 * @package WFO
 */

namespace WFO\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use WFO\Menu_Repository;
use WFO\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Places the ordering app on an Elementor page.
 *
 * All markup comes from {@see \WFO\Render::menu()}; this class only maps panel
 * settings to the documented render args and design tokens.
 */
class Widget_Menu extends Widget_Base {

	/**
	 * Widget slug used by Elementor and by the front-end JS.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'wfo_menu';
	}

	/**
	 * Panel title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Food Menu & WhatsApp Order', 'whatsapp-menu-orders' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-menu-bar';
	}

	/**
	 * Panel category.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( Manager::CATEGORY );
	}

	/**
	 * Panel search keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'menu', 'food', 'order', 'whatsapp', 'restaurant' );
	}

	/**
	 * Stylesheet handles registered by WFO\Assets.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( 'wfo-front' );
	}

	/**
	 * Script handles registered by WFO\Assets.
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array( 'wfo-front' );
	}

	/**
	 * Panel controls.
	 */
	protected function register_controls() {
		$this->register_content_controls();
		$this->register_style_controls();
	}

	/**
	 * "Menu" tab.
	 */
	private function register_content_controls() {
		$this->start_controls_section(
			'section_menu',
			array(
				'label' => esc_html__( 'Menu', 'whatsapp-menu-orders' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'categories',
			array(
				'label'       => esc_html__( 'Categories', 'whatsapp-menu-orders' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->category_options(),
				'description' => esc_html__( 'Leave empty to show every category.', 'whatsapp-menu-orders' ),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => esc_html__( 'Layout', 'whatsapp-menu-orders' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid' => esc_html__( 'Grid', 'whatsapp-menu-orders' ),
					'list' => esc_html__( 'List', 'whatsapp-menu-orders' ),
				),
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'     => esc_html__( 'Columns', 'whatsapp-menu-orders' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '3',
				'options'   => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'condition' => array(
					'layout' => 'grid',
				),
			)
		);

		$this->add_control(
			'show_images',
			array(
				'label'   => esc_html__( 'Show images', 'whatsapp-menu-orders' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_description',
			array(
				'label'   => esc_html__( 'Show descriptions', 'whatsapp-menu-orders' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_catbar',
			array(
				'label'   => esc_html__( 'Show category bar', 'whatsapp-menu-orders' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_search',
			array(
				'label'       => esc_html__( 'Show search', 'whatsapp-menu-orders' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => esc_html__( 'Search must also be enabled under Food Orders → Settings.', 'whatsapp-menu-orders' ),
			)
		);

		$this->add_control(
			'sticky_cart',
			array(
				'label'   => esc_html__( 'Sticky cart', 'whatsapp-menu-orders' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'       => esc_html__( 'Maximum items', 'whatsapp-menu-orders' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => -1,
				'step'        => 1,
				'default'     => -1,
				'description' => esc_html__( 'Use -1 to show every item.', 'whatsapp-menu-orders' ),
			)
		);

		$this->add_control(
			'menu_help',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: %s: link to the menu items screen in the WordPress admin. */
					esc_html__( 'Add your dishes under %s, then choose which categories to show here.', 'whatsapp-menu-orders' ),
					'<a href="' . esc_url( admin_url( 'edit.php?post_type=' . $this->item_post_type() ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Food Orders → Menu Items', 'whatsapp-menu-orders' ) . '</a>'
				),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * "Design" tab. Everything writes the design tokens consumed by assets/css/wfo.css.
	 */
	private function register_style_controls() {
		$this->start_controls_section(
			'section_design',
			array(
				'label' => esc_html__( 'Design', 'whatsapp-menu-orders' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'accent',
			array(
				'label'     => esc_html__( 'Accent color', 'whatsapp-menu-orders' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wfo' => '--wfo-accent: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'ink',
			array(
				'label'     => esc_html__( 'Text color', 'whatsapp-menu-orders' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wfo' => '--wfo-ink: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'muted',
			array(
				'label'     => esc_html__( 'Muted text color', 'whatsapp-menu-orders' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wfo' => '--wfo-muted: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'card_bg',
			array(
				'label'     => esc_html__( 'Card background', 'whatsapp-menu-orders' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wfo' => '--wfo-surface: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'card_border',
			array(
				'label'     => esc_html__( 'Card border color', 'whatsapp-menu-orders' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wfo' => '--wfo-line: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_bg',
			array(
				'label'     => esc_html__( 'Button background', 'whatsapp-menu-orders' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wfo' => '--wfo-accent: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'     => esc_html__( 'Button text color', 'whatsapp-menu-orders' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wfo' => '--wfo-accent-ink: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'radius',
			array(
				'label'      => esc_html__( 'Corner radius', 'whatsapp-menu-orders' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 40,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .wfo' => '--wfo-radius: {{SIZE}}{{UNIT}}; --wfo-radius-lg: calc({{SIZE}}{{UNIT}} + 8px);',
				),
			)
		);

		$this->add_control(
			'gap',
			array(
				'label'      => esc_html__( 'Card gap', 'whatsapp-menu-orders' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 8,
						'max'  => 40,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .wfo' => '--wfo-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'card_padding',
			array(
				'label'      => esc_html__( 'Card padding', 'whatsapp-menu-orders' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 64,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .wfo' => '--wfo-card-pad: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'card_frame',
				'label'    => esc_html__( 'Card border', 'whatsapp-menu-orders' ),
				'selector' => '{{WRAPPER}} .wfo-card',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'card_title_typography',
				'label'    => esc_html__( 'Card title', 'whatsapp-menu-orders' ),
				'selector' => '{{WRAPPER}} .wfo-card__title',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'price_typography',
				'label'    => esc_html__( 'Price', 'whatsapp-menu-orders' ),
				'selector' => '{{WRAPPER}} .wfo-price',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'label'    => esc_html__( 'Button', 'whatsapp-menu-orders' ),
				'selector' => '{{WRAPPER}} .wfo-btn',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'catbar_typography',
				'label'    => esc_html__( 'Category bar', 'whatsapp-menu-orders' ),
				'selector' => '{{WRAPPER}} .wfo-catbar a',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Category id => name for the multi-select.
	 *
	 * @return array
	 */
	private function category_options() {
		$options = array();

		foreach ( Menu_Repository::categories( false ) as $category ) {
			$options[ (string) $category['id'] ] = $category['name'];
		}

		return $options;
	}

	/**
	 * Menu item post type, from the plugin constant with a safe fallback.
	 *
	 * @return string
	 */
	private function item_post_type() {
		return defined( 'WFO_CPT_ITEM' ) ? WFO_CPT_ITEM : 'wfo_item';
	}

	/**
	 * Render the ordering app.
	 */
	protected function render() {
		if ( ! class_exists( Render::class ) ) {
			return;
		}

		$settings = $this->get_settings_for_display();

		$categories = array();

		if ( ! empty( $settings['categories'] ) ) {
			$categories = array_values( array_filter( array_map( 'absint', (array) $settings['categories'] ) ) );
		}

		$limit = ( isset( $settings['limit'] ) && '' !== $settings['limit'] ) ? (int) $settings['limit'] : -1;

		$args = array(
			'categories'       => $categories,
			'layout'           => ( isset( $settings['layout'] ) && 'list' === $settings['layout'] ) ? 'list' : 'grid',
			'columns'          => isset( $settings['columns'] ) ? max( 1, min( 4, (int) $settings['columns'] ) ) : 3,
			'show_images'      => $this->is_switched_on( $settings, 'show_images' ),
			'show_description' => $this->is_switched_on( $settings, 'show_description' ),
			'show_catbar'      => $this->is_switched_on( $settings, 'show_catbar' ),
			'show_search'      => $this->is_switched_on( $settings, 'show_search' ),
			'sticky_cart'      => $this->is_switched_on( $settings, 'sticky_cart' ),
			'limit'            => $limit < -1 ? -1 : $limit,
			'instance'         => $this->instance_id(),
		);

		// Markup is escaped inside WFO\Render and its templates.
		echo Render::menu( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Interpret an Elementor switcher or select value as a boolean.
	 *
	 * Elementor switchers store `yes` when on and an empty string when off; a value
	 * that was never saved falls back to the control default (on).
	 *
	 * @param array  $settings Widget settings.
	 * @param string $key      Setting key.
	 * @return bool
	 */
	private function is_switched_on( $settings, $key ) {
		if ( ! isset( $settings[ $key ] ) ) {
			return true;
		}

		return in_array( $settings[ $key ], array( 'yes', '1', 'true', true ), true );
	}

	/**
	 * Stable per-widget instance id so carts survive reloads.
	 *
	 * @return string
	 */
	private function instance_id() {
		$id = sanitize_key( (string) $this->get_id() );

		return '' === $id ? '' : 'wfo-' . $id;
	}
}
