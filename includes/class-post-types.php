<?php
/**
 * Custom post types, the menu category taxonomy and the Food Orders admin menu.
 *
 * @package WFO
 */

namespace WFO;

use WFO\Admin\Orders_Admin;
use WFO\Admin\Settings_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's data types and its admin menu.
 */
class Post_Types {

	/**
	 * Top level admin menu slug.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'wfo';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 9 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_submenus' ), 20 );
		add_filter( 'plugin_action_links_' . plugin_basename( WFO_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );

		/*
		 * The cleanup routine lives in the admin module, but Plugin::boot() only loads
		 * the admin modules inside wp-admin. The hook has to exist on cron requests
		 * too, so it is registered here, where init() always runs.
		 */
		add_action( 'wfo_cleanup_orders', array( Orders_Admin::class, 'cleanup_orders' ) );
	}

	/**
	 * Register the post types and taxonomy.
	 *
	 * Safe to call more than once: activation calls it directly and init calls it
	 * on every request.
	 */
	public static function register() {
		register_post_type(
			WFO_CPT_ITEM,
			array(
				'labels'              => array(
					'name'                  => __( 'Menu items', 'whatsapp-menu-orders' ),
					'singular_name'         => __( 'Menu item', 'whatsapp-menu-orders' ),
					'menu_name'             => __( 'Menu items', 'whatsapp-menu-orders' ),
					'all_items'             => __( 'Menu items', 'whatsapp-menu-orders' ),
					'add_new'               => __( 'Add menu item', 'whatsapp-menu-orders' ),
					'add_new_item'          => __( 'Add menu item', 'whatsapp-menu-orders' ),
					'edit_item'             => __( 'Edit menu item', 'whatsapp-menu-orders' ),
					'new_item'              => __( 'New menu item', 'whatsapp-menu-orders' ),
					'view_item'             => __( 'View menu item', 'whatsapp-menu-orders' ),
					'view_items'            => __( 'View menu items', 'whatsapp-menu-orders' ),
					'search_items'          => __( 'Search menu items', 'whatsapp-menu-orders' ),
					'not_found'             => __( 'No menu items found.', 'whatsapp-menu-orders' ),
					'not_found_in_trash'    => __( 'No menu items found in Trash.', 'whatsapp-menu-orders' ),
					'item_published'        => __( 'Menu item published.', 'whatsapp-menu-orders' ),
					'item_updated'          => __( 'Menu item updated.', 'whatsapp-menu-orders' ),
					'featured_image'        => __( 'Photo', 'whatsapp-menu-orders' ),
					'set_featured_image'    => __( 'Set photo', 'whatsapp-menu-orders' ),
					'remove_featured_image' => __( 'Remove photo', 'whatsapp-menu-orders' ),
					'use_featured_image'    => __( 'Use as photo', 'whatsapp-menu-orders' ),
				),
				'description'         => __( 'Dishes and drinks shown on the ordering page.', 'whatsapp-menu-orders' ),
				'public'              => true,
				'has_archive'         => false,
				'show_in_menu'        => self::MENU_SLUG,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
				'rewrite'             => array( 'slug' => 'menu' ),
				'exclude_from_search' => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-food',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);

		register_taxonomy(
			WFO_TAX_CAT,
			array( WFO_CPT_ITEM ),
			array(
				'labels'            => array(
					'name'              => __( 'Menu categories', 'whatsapp-menu-orders' ),
					'singular_name'     => __( 'Menu category', 'whatsapp-menu-orders' ),
					'menu_name'         => __( 'Categories', 'whatsapp-menu-orders' ),
					'all_items'         => __( 'All categories', 'whatsapp-menu-orders' ),
					'edit_item'         => __( 'Edit category', 'whatsapp-menu-orders' ),
					'view_item'         => __( 'View category', 'whatsapp-menu-orders' ),
					'update_item'       => __( 'Update category', 'whatsapp-menu-orders' ),
					'add_new_item'      => __( 'Add new category', 'whatsapp-menu-orders' ),
					'new_item_name'     => __( 'New category name', 'whatsapp-menu-orders' ),
					'parent_item'       => __( 'Parent category', 'whatsapp-menu-orders' ),
					'parent_item_colon' => __( 'Parent category:', 'whatsapp-menu-orders' ),
					'search_items'      => __( 'Search categories', 'whatsapp-menu-orders' ),
					'not_found'         => __( 'No categories found.', 'whatsapp-menu-orders' ),
					'back_to_items'     => __( 'Back to categories', 'whatsapp-menu-orders' ),
				),
				'description'       => __( 'Groups the menu into sections such as Burgers or Drinks.', 'whatsapp-menu-orders' ),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'menu-category' ),
			)
		);

		register_post_type(
			WFO_CPT_ORDER,
			array(
				'labels'              => array(
					'name'               => __( 'Orders', 'whatsapp-menu-orders' ),
					'singular_name'      => __( 'Order', 'whatsapp-menu-orders' ),
					'menu_name'          => __( 'Orders', 'whatsapp-menu-orders' ),
					'all_items'          => __( 'Orders', 'whatsapp-menu-orders' ),
					'edit_item'          => __( 'Edit order', 'whatsapp-menu-orders' ),
					'view_item'          => __( 'View order', 'whatsapp-menu-orders' ),
					'search_items'       => __( 'Search orders', 'whatsapp-menu-orders' ),
					'not_found'          => __( 'No orders found.', 'whatsapp-menu-orders' ),
					'not_found_in_trash' => __( 'No orders found in Trash.', 'whatsapp-menu-orders' ),
				),
				'description'         => __( 'Orders sent from the menu page, kept for the restaurant records.', 'whatsapp-menu-orders' ),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => self::MENU_SLUG,
				'supports'            => array( 'title' ),
				// Customer data: only administrators may read orders.
				'capability_type'     => array( 'wfo_order', 'wfo_orders' ),
				'map_meta_cap'        => true,
				'rewrite'             => false,
				'query_var'           => false,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-clipboard',
			)
		);
	}

	/**
	 * Register the top level Food Orders menu.
	 *
	 * Runs before _add_post_type_submenus() so the post type submenus attach to an
	 * existing menu, and the dashboard is the first submenu item.
	 */
	public static function admin_menu() {
		add_menu_page(
			__( 'Food Orders', 'whatsapp-menu-orders' ),
			__( 'Food Orders', 'whatsapp-menu-orders' ),
			'edit_posts',
			self::MENU_SLUG,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-store',
			26
		);

		global $submenu;

		if ( ! isset( $submenu[ self::MENU_SLUG ] ) || ! is_array( $submenu[ self::MENU_SLUG ] ) ) {
			$submenu[ self::MENU_SLUG ] = array();
		}

		$submenu[ self::MENU_SLUG ][0] = array(
			__( 'Dashboard', 'whatsapp-menu-orders' ),
			'edit_posts',
			self::MENU_SLUG,
			__( 'Dashboard', 'whatsapp-menu-orders' ),
		);
	}

	/**
	 * Add the Settings, Extras and Categories submenus.
	 *
	 * Categories is a plain wp-admin link (edit-tags.php), which has to be added to
	 * the submenu array directly; the Screen API cannot register an edit-tags.php
	 * page as a plugin page.
	 */
	public static function admin_submenus() {
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'whatsapp-menu-orders' ),
			__( 'Settings', 'whatsapp-menu-orders' ),
			'manage_options',
			'wfo-settings',
			array( Settings_Page::class, 'render' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Extras', 'whatsapp-menu-orders' ),
			__( 'Extras', 'whatsapp-menu-orders' ),
			'manage_options',
			'wfo-extras',
			array( Settings_Page::class, 'render_extras' )
		);

		global $submenu;

		if ( ! isset( $submenu[ self::MENU_SLUG ] ) || ! is_array( $submenu[ self::MENU_SLUG ] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		$items    = $submenu[ self::MENU_SLUG ];
		$ordered  = array();
		$inserted = false;

		foreach ( $items as $key => $item ) {
			$ordered[ $key ] = $item;

			if ( ! $inserted && isset( $item[2] ) && 'edit.php?post_type=' . WFO_CPT_ITEM === $item[2] ) {
				$ordered['wfo-categories'] = array(
					__( 'Categories', 'whatsapp-menu-orders' ),
					'manage_categories',
					'edit-tags.php?taxonomy=' . WFO_TAX_CAT . '&amp;post_type=' . WFO_CPT_ITEM,
				);
				$inserted = true;
			}
		}

		$submenu[ self::MENU_SLUG ] = $ordered;
	}

	/**
	 * Add a Settings link to the plugin row.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wfo-settings' ) ),
			esc_html__( 'Settings', 'whatsapp-menu-orders' )
		);

		array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * Dashboard styles.
	 */
	public static function admin_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'toplevel_page_' . self::MENU_SLUG !== $screen->id ) {
			return;
		}

		wp_enqueue_style( 'wfo-admin', WFO_URL . 'assets/css/wfo-admin.css', array(), WFO_VERSION );
	}

	/**
	 * The Food Orders dashboard: recent orders and a setup checklist.
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view orders.', 'whatsapp-menu-orders' ) );
		}

		$can_orders = Capabilities::can_manage_orders();

		$orders = $can_orders
			? new \WP_Query(
				array(
					'post_type'           => WFO_CPT_ORDER,
					'post_status'         => 'any',
					'posts_per_page'      => 5,
					'orderby'             => 'date',
					'order'               => 'DESC',
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
				)
			)
			: null;

		$checklist  = self::checklist();
		$page_id    = (int) Settings::get( 'menu_page_id' );
		$page_url   = $page_id ? get_permalink( $page_id ) : '';
		$orders_url = admin_url( 'edit.php?post_type=' . WFO_CPT_ORDER );
		?>
		<div class="wrap wfo-dash">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Food Orders', 'whatsapp-menu-orders' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . WFO_CPT_ITEM ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add menu item', 'whatsapp-menu-orders' ); ?></a>
			<?php if ( $page_url ) : ?>
				<a href="<?php echo esc_url( $page_url ); ?>" class="page-title-action" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View menu page', 'whatsapp-menu-orders' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<div class="wfo-dash__grid">
				<?php if ( $orders ) : ?>
				<div class="postbox wfo-dash__panel">
					<h2 class="hndle"><span><?php esc_html_e( 'Recent orders', 'whatsapp-menu-orders' ); ?></span></h2>
					<div class="inside">
						<?php if ( $orders->have_posts() ) : ?>
							<table class="widefat striped wfo-dash__orders">
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Code', 'whatsapp-menu-orders' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Customer', 'whatsapp-menu-orders' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Total', 'whatsapp-menu-orders' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Status', 'whatsapp-menu-orders' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									foreach ( $orders->posts as $order_post ) :
										$data   = Orders_Admin::get_order_data( $order_post->ID );
										$status = Orders_Admin::status( $data ? $data['status'] : 'new' );
										$total  = $data ? (int) $data['totals']['total'] : 0;
										$name   = $data && '' !== trim( (string) $data['customer']['name'] ) ? $data['customer']['name'] : __( 'Guest', 'whatsapp-menu-orders' );
										$code   = $data && '' !== $data['code'] ? $data['code'] : get_the_title( $order_post );
										?>
										<tr>
											<td>
												<a class="row-title" href="<?php echo esc_url( (string) get_edit_post_link( $order_post->ID ) ); ?>"><?php echo esc_html( $code ); ?></a>
												<br>
												<span class="wfo-muted"><?php echo esc_html( get_the_date( '', $order_post ) . ' · ' . get_the_time( '', $order_post ) ); ?></span>
											</td>
											<td><?php echo esc_html( $name ); ?></td>
											<td><?php echo esc_html( Money::format( $total ) ); ?></td>
											<td><span class="wfo-status wfo-status--<?php echo esc_attr( $status['key'] ); ?>"><?php echo esc_html( $status['label'] ); ?></span></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							<p class="wfo-dash__more">
								<a class="button" href="<?php echo esc_url( $orders_url ); ?>"><?php esc_html_e( 'View all orders', 'whatsapp-menu-orders' ); ?></a>
							</p>
						<?php else : ?>
							<p class="wfo-empty"><?php esc_html_e( 'No orders yet. Orders sent from your menu page show up here.', 'whatsapp-menu-orders' ); ?></p>
							<p>
								<?php if ( $page_url ) : ?>
									<a class="button button-primary" href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open the menu page', 'whatsapp-menu-orders' ); ?></a>
								<?php else : ?>
									<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wfo-settings' ) ); ?>"><?php esc_html_e( 'Finish setting up', 'whatsapp-menu-orders' ); ?></a>
								<?php endif; ?>
							</p>
						<?php endif; ?>
						<?php wp_reset_postdata(); ?>
					</div>
				</div>
				<?php endif; ?>

				<div class="postbox wfo-dash__panel">
					<h2 class="hndle"><span><?php esc_html_e( 'Set up your menu', 'whatsapp-menu-orders' ); ?></span></h2>
					<div class="inside">
						<ul class="wfo-checklist">
							<?php foreach ( $checklist as $check ) : ?>
								<li class="wfo-checklist__item <?php echo $check['done'] ? 'is-done' : 'is-todo'; ?>">
									<span class="wfo-dot <?php echo $check['done'] ? 'wfo-dot--ok' : 'wfo-dot--todo'; ?>" aria-hidden="true"></span>
									<span class="screen-reader-text"><?php echo $check['done'] ? esc_html__( 'Done', 'whatsapp-menu-orders' ) : esc_html__( 'To do', 'whatsapp-menu-orders' ); ?></span>
									<span class="wfo-checklist__body">
										<span class="wfo-checklist__label"><?php echo esc_html( $check['label'] ); ?></span>
										<span class="wfo-checklist__detail"><?php echo esc_html( $check['detail'] ); ?></span>
									</span>
									<a class="wfo-checklist__action" href="<?php echo esc_url( $check['url'] ); ?>"><?php echo esc_html( $check['action'] ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Setup checklist rows.
	 *
	 * @return array<int,array{done:bool,label:string,detail:string,url:string,action:string}>
	 */
	private static function checklist() {
		$settings  = Settings::all();
		$counts    = wp_count_posts( WFO_CPT_ITEM );
		$published = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$drafts    = 0;

		foreach ( array( 'draft', 'pending', 'future', 'private' ) as $state ) {
			if ( isset( $counts->$state ) ) {
				$drafts += (int) $counts->$state;
			}
		}

		$terms     = wp_count_terms(
			array(
				'taxonomy'   => WFO_TAX_CAT,
				'hide_empty' => false,
			)
		);
		$cat_count = is_wp_error( $terms ) ? 0 : (int) $terms;

		$has_number = '' !== (string) $settings['whatsapp_number'];

		if ( $published > 0 ) {
			/* translators: %s: number of published menu items. */
			$items_detail = sprintf( _n( '%s item published.', '%s items published.', $published, 'whatsapp-menu-orders' ), number_format_i18n( $published ) );
		} elseif ( $drafts > 0 ) {
			/* translators: %s: number of unpublished menu items. */
			$items_detail = sprintf( _n( '%s unpublished item waiting.', '%s unpublished items waiting.', $drafts, 'whatsapp-menu-orders' ), number_format_i18n( $drafts ) );
		} else {
			$items_detail = __( 'Add your first dish or drink.', 'whatsapp-menu-orders' );
		}

		if ( $cat_count > 0 ) {
			/* translators: %s: number of menu categories. */
			$cats_detail = sprintf( _n( '%s category.', '%s categories.', $cat_count, 'whatsapp-menu-orders' ), number_format_i18n( $cat_count ) );
		} else {
			$cats_detail = __( 'Categories become the sections of your menu.', 'whatsapp-menu-orders' );
		}

		/* translators: 1: currency code, 2: example amount. */
		$currency_detail = sprintf( __( 'Amounts use %1$s, for example %2$s.', 'whatsapp-menu-orders' ), (string) $settings['currency'], Money::format( 0, $settings ) );

		return array(
			array(
				'done'   => $has_number,
				'label'  => __( 'WhatsApp number set', 'whatsapp-menu-orders' ),
				'detail' => $has_number ? __( 'Orders are sent to this number.', 'whatsapp-menu-orders' ) : __( 'Add the number that receives orders.', 'whatsapp-menu-orders' ),
				'url'    => admin_url( 'admin.php?page=wfo-settings&tab=whatsapp' ),
				'action' => $has_number ? __( 'Change', 'whatsapp-menu-orders' ) : __( 'Add number', 'whatsapp-menu-orders' ),
			),
			array(
				'done'   => '' !== (string) $settings['currency'],
				'label'  => __( 'Currency set', 'whatsapp-menu-orders' ),
				'detail' => $currency_detail,
				'url'    => admin_url( 'admin.php?page=wfo-settings&tab=general' ),
				'action' => __( 'Change', 'whatsapp-menu-orders' ),
			),
			array(
				'done'   => $published > 0,
				'label'  => __( 'At least one menu item', 'whatsapp-menu-orders' ),
				'detail' => $items_detail,
				'url'    => $published > 0 ? admin_url( 'edit.php?post_type=' . WFO_CPT_ITEM ) : admin_url( 'post-new.php?post_type=' . WFO_CPT_ITEM ),
				'action' => $published > 0 ? __( 'Manage items', 'whatsapp-menu-orders' ) : __( 'Add item', 'whatsapp-menu-orders' ),
			),
			array(
				'done'   => $cat_count > 0,
				'label'  => __( 'At least one category', 'whatsapp-menu-orders' ),
				'detail' => $cats_detail,
				'url'    => admin_url( 'edit-tags.php?taxonomy=' . WFO_TAX_CAT . '&post_type=' . WFO_CPT_ITEM ),
				'action' => $cat_count > 0 ? __( 'Manage categories', 'whatsapp-menu-orders' ) : __( 'Add category', 'whatsapp-menu-orders' ),
			),
		);
	}
}
