<?php
/**
 * Orders list, order details, status actions, CSV export and retention.
 *
 * @package WFO
 */

namespace WFO\Admin;

use WFO\Capabilities;
use WFO\Money;
use WFO\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Everything the restaurant needs to read and manage submitted orders.
 */
class Orders_Admin {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'manage_' . WFO_CPT_ORDER . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . WFO_CPT_ORDER . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . WFO_CPT_ORDER . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );

		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . WFO_CPT_ORDER, array( __CLASS__, 'save_status' ), 10, 2 );

		add_filter( 'bulk_actions-edit-' . WFO_CPT_ORDER, array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . WFO_CPT_ORDER, array( __CLASS__, 'handle_bulk' ), 10, 3 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );

		add_action( 'restrict_manage_posts', array( __CLASS__, 'export_button' ) );
		add_action( 'admin_post_wfo_export_orders', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_wfo_order_status', array( __CLASS__, 'handle_status_action' ) );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		/*
		 * The cron callback itself is registered in WFO\Post_Types: Post_Types boots on
		 * every request, while the admin modules are only loaded inside wp-admin and
		 * wp-cron.php is not an admin request.
		 */
		self::sync_retention();
	}

	/**
	 * List columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		return array(
			'cb'            => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
			'wfo_code'      => __( 'Code', 'whatsapp-menu-orders' ),
			'wfo_date'      => __( 'Date / time', 'whatsapp-menu-orders' ),
			'wfo_customer'  => __( 'Customer', 'whatsapp-menu-orders' ),
			'wfo_phone'     => __( 'Phone', 'whatsapp-menu-orders' ),
			'wfo_type'      => __( 'Type', 'whatsapp-menu-orders' ),
			'wfo_payment'   => __( 'Payment', 'whatsapp-menu-orders' ),
			'wfo_total'     => __( 'Total', 'whatsapp-menu-orders' ),
			'wfo_status'    => __( 'Status', 'whatsapp-menu-orders' ),
		);
	}

	/**
	 * Sortable columns. Date is the default order.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable_columns( $columns ) {
		$columns['wfo_date'] = 'date';
		$columns['wfo_code'] = 'title';

		return $columns;
	}

	/**
	 * Column output.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Order id.
	 */
	public static function column( $column, $post_id ) {
		$data = self::get_order_data( $post_id );

		if ( ! $data ) {
			return;
		}

		$status = self::status( $data['status'] );

		switch ( $column ) {
			case 'wfo_code':
				printf(
					'<strong><a class="row-title" href="%s">%s</a></strong>',
					esc_url( (string) get_edit_post_link( $post_id ) ),
					esc_html( $data['code'] )
				);
				break;

			case 'wfo_date':
				echo esc_html( self::date_label( $post_id ) );
				break;

			case 'wfo_customer':
				$name = trim( (string) $data['customer']['name'] );
				echo esc_html( '' !== $name ? $name : __( 'Guest', 'whatsapp-menu-orders' ) );
				break;

			case 'wfo_phone':
				$phone = trim( (string) $data['customer']['phone'] );

				if ( '' === $phone ) {
					echo '<span class="wfo-muted">—</span>';
					break;
				}

				$digits = preg_replace( '/[^\d+]/', '', $phone );
				printf( '<a href="%s">%s</a>', esc_url( 'tel:' . $digits ), esc_html( $phone ) );
				break;

			case 'wfo_type':
				echo esc_html( self::type_label( $data['type'] ) );
				break;

			case 'wfo_payment':
				echo esc_html( self::payment_label( $data['payment'] ) );
				break;

			case 'wfo_total':
				printf( '<strong>%s</strong>', esc_html( Money::format( (int) $data['totals']['total'] ) ) );
				break;

			case 'wfo_status':
				printf(
					'<span class="wfo-status wfo-status--%s">%s</span>',
					esc_attr( $status['key'] ),
					esc_html( $status['label'] )
				);
				break;
		}
	}

	/**
	 * Register the order details meta box.
	 */
	public static function meta_boxes() {
		add_meta_box(
			'wfo_order_details',
			__( 'Order details', 'whatsapp-menu-orders' ),
			array( __CLASS__, 'render_meta_box' ),
			WFO_CPT_ORDER,
			'normal',
			'high'
		);
	}

	/**
	 * Display an order.
	 *
	 * @param \WP_Post $post Order post.
	 */
	public static function render_meta_box( $post ) {
		$data = self::get_order_data( $post->ID );

		if ( ! $data ) {
			echo '<p>' . esc_html__( 'No details were stored for this order.', 'whatsapp-menu-orders' ) . '</p>';
			return;
		}

		$status  = self::status( $data['status'] );
		$message = (string) $data['message'];
		$wa_url  = self::wa_url( $message );
		?>
		<div class="wfo-order">
			<p class="wfo-order__code"><?php echo esc_html( $data['code'] ); ?></p>

			<?php if ( $data['items'] ) : ?>
				<ul class="wfo-order-lines">
					<?php foreach ( $data['items'] as $line ) : ?>
						<li class="wfo-order-line">
							<div class="wfo-order-line__main">
								<span class="wfo-order-line__qty"><?php echo esc_html( $line['qty'] . '×' ); ?></span>
								<span class="wfo-order-line__name"><?php echo esc_html( $line['name'] ); ?></span>
								<?php if ( null !== $line['total'] ) : ?>
									<span class="wfo-order-line__total"><?php echo esc_html( Money::format( (int) $line['total'] ) ); ?></span>
								<?php endif; ?>
							</div>

							<?php if ( $line['options'] ) : ?>
								<div class="wfo-order-line__opts">
									<?php foreach ( $line['options'] as $option ) : ?>
										<span class="wfo-order-line__opt">
											+ <?php echo esc_html( $option['label'] ); ?>
											<?php if ( null !== $option['price'] && 0 !== (int) $option['price'] ) : ?>
												<span class="wfo-muted">(<?php echo esc_html( Money::format( (int) $option['price'] ) ); ?>)</span>
											<?php endif; ?>
										</span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<?php if ( '' !== $line['note'] ) : ?>
								<div class="wfo-order-line__note">· <?php echo esc_html( $line['note'] ); ?></div>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="wfo-muted"><?php esc_html_e( 'No items were stored for this order.', 'whatsapp-menu-orders' ); ?></p>
			<?php endif; ?>

			<table class="wfo-order-totals">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Subtotal', 'whatsapp-menu-orders' ); ?></th>
						<td><?php echo esc_html( Money::format( (int) $data['totals']['subtotal'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Delivery', 'whatsapp-menu-orders' ); ?></th>
						<td>
							<?php
							echo (int) $data['totals']['delivery'] > 0
								? esc_html( Money::format( (int) $data['totals']['delivery'] ) )
								: esc_html__( '—', 'whatsapp-menu-orders' );
							?>
						</td>
					</tr>
					<tr class="wfo-order-totals__total">
						<th scope="row"><?php esc_html_e( 'Total', 'whatsapp-menu-orders' ); ?></th>
						<td><?php echo esc_html( Money::format( (int) $data['totals']['total'] ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<div class="wfo-order-customer">
				<p>
					<span class="wfo-muted"><?php esc_html_e( 'Customer', 'whatsapp-menu-orders' ); ?></span><br>
					<strong><?php echo esc_html( '' !== trim( (string) $data['customer']['name'] ) ? $data['customer']['name'] : __( 'Guest', 'whatsapp-menu-orders' ) ); ?></strong>
				</p>

				<?php if ( '' !== trim( (string) $data['customer']['phone'] ) ) : ?>
					<p>
						<span class="wfo-muted"><?php esc_html_e( 'Phone', 'whatsapp-menu-orders' ); ?></span><br>
						<a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', (string) $data['customer']['phone'] ) ); ?>"><?php echo esc_html( $data['customer']['phone'] ); ?></a>
					</p>
				<?php endif; ?>

				<?php if ( '' !== trim( (string) $data['customer']['address'] ) ) : ?>
					<p>
						<span class="wfo-muted"><?php esc_html_e( 'Address', 'whatsapp-menu-orders' ); ?></span><br>
						<?php echo nl2br( make_clickable( esc_html( $data['customer']['address'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped before links are added. ?>
					</p>
				<?php endif; ?>

				<?php if ( '' !== trim( (string) $data['customer']['time'] ) ) : ?>
					<p>
						<span class="wfo-muted"><?php esc_html_e( 'When', 'whatsapp-menu-orders' ); ?></span><br>
						<?php echo esc_html( $data['customer']['time'] ); ?>
					</p>
				<?php endif; ?>

				<?php if ( '' !== trim( (string) $data['customer']['note'] ) ) : ?>
					<p>
						<span class="wfo-muted"><?php esc_html_e( 'Note', 'whatsapp-menu-orders' ); ?></span><br>
						<?php echo nl2br( esc_html( $data['customer']['note'] ) ); ?>
					</p>
				<?php endif; ?>
			</div>

			<p><span class="wfo-muted"><?php esc_html_e( 'Type', 'whatsapp-menu-orders' ); ?>:</span> <?php echo esc_html( self::type_label( $data['type'] ) ); ?></p>
			<p><span class="wfo-muted"><?php esc_html_e( 'Payment', 'whatsapp-menu-orders' ); ?>:</span> <?php echo esc_html( self::payment_label( $data['payment'] ) ); ?></p>

			<p>
				<label for="wfo-order-status"><strong><?php esc_html_e( 'Status', 'whatsapp-menu-orders' ); ?></strong></label><br>
				<select name="wfo_status" id="wfo-order-status">
					<option value="new" <?php selected( $status['key'], 'new' ); ?>><?php esc_html_e( 'New', 'whatsapp-menu-orders' ); ?></option>
					<option value="handled" <?php selected( $status['key'], 'handled' ); ?>><?php esc_html_e( 'Handled', 'whatsapp-menu-orders' ); ?></option>
				</select>
				<span class="description"><?php esc_html_e( 'Saves with the order.', 'whatsapp-menu-orders' ); ?></span>
			</p>

			<?php wp_nonce_field( 'wfo_order_status', 'wfo_order_status_nonce' ); ?>

			<p><strong><?php esc_html_e( 'WhatsApp message', 'whatsapp-menu-orders' ); ?></strong></p>
			<pre class="wfo-order-message"><?php echo esc_html( $message ); ?></pre>

			<?php if ( '' !== $wa_url ) : ?>
				<p>
					<?php
					/*
					 * esc_url() strips encoded newlines (%0A), which would flatten the
					 * prefilled order message. The URL is generated by WFO\Order::wa_url()
					 * with a fixed https://wa.me prefix, so attribute escaping is correct.
					 */
					?>
					<a class="button button-secondary" href="<?php echo esc_attr( $wa_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open in WhatsApp', 'whatsapp-menu-orders' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Persist the status select from the order edit screen.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post object.
	 */
	public static function save_status( $post_id, $post ) {
		if ( ! $post instanceof \WP_Post || WFO_CPT_ORDER !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['wfo_order_status_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['wfo_order_status_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'wfo_order_status' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$status = ( isset( $_POST['wfo_status'] ) && 'handled' === $_POST['wfo_status'] ) ? 'handled' : 'new';

		self::set_status( $post_id, $status );
	}

	/**
	 * Status actions offered in the bulk actions dropdown.
	 *
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public static function bulk_actions( $actions ) {
		$actions['wfo_handled'] = __( 'Mark as handled', 'whatsapp-menu-orders' );
		$actions['wfo_new']     = __( 'Mark as new', 'whatsapp-menu-orders' );

		return $actions;
	}

	/**
	 * Handle the bulk status actions.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Bulk action.
	 * @param array  $post_ids    Selected order ids.
	 * @return string
	 */
	public static function handle_bulk( $redirect_to, $action, $post_ids ) {
		if ( ! in_array( $action, array( 'wfo_handled', 'wfo_new' ), true ) ) {
			return $redirect_to;
		}

		check_admin_referer( 'bulk-posts' );

		$status = 'wfo_handled' === $action ? 'handled' : 'new';
		$count  = 0;

		foreach ( (array) $post_ids as $post_id ) {
			$post_id = absint( $post_id );

			if ( ! $post_id || WFO_CPT_ORDER !== get_post_type( $post_id ) ) {
				continue;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			self::set_status( $post_id, $status );
			++$count;
		}

		return add_query_arg(
			array(
				'wfo_marked'      => $count,
				'wfo_mark_status' => $status,
			),
			$redirect_to
		);
	}

	/**
	 * Row actions to toggle an order status.
	 *
	 * @param array    $actions Existing actions.
	 * @param \WP_Post $post    Post object.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( ! $post instanceof \WP_Post || WFO_CPT_ORDER !== $post->post_type ) {
			return $actions;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$data   = self::get_order_data( $post->ID );
		$target = ( $data && 'handled' === $data['status'] ) ? 'new' : 'handled';
		$url    = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'wfo_order_status',
					'order'  => $post->ID,
					'status' => $target,
				),
				admin_url( 'admin-post.php' )
			),
			'wfo_order_status_' . $post->ID
		);

		$actions['wfo_status'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html( 'handled' === $target ? __( 'Mark as handled', 'whatsapp-menu-orders' ) : __( 'Mark as new', 'whatsapp-menu-orders' ) )
		);

		return $actions;
	}

	/**
	 * Single order status action (row action link).
	 */
	public static function handle_status_action() {
		$order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;

		if ( ! $order_id ) {
			wp_die( esc_html__( 'No order was selected.', 'whatsapp-menu-orders' ) );
		}

		check_admin_referer( 'wfo_order_status_' . $order_id );

		if ( ! current_user_can( 'edit_post', $order_id ) || WFO_CPT_ORDER !== get_post_type( $order_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to update this order.', 'whatsapp-menu-orders' ) );
		}

		$status = ( isset( $_GET['status'] ) && 'handled' === $_GET['status'] ) ? 'handled' : 'new';

		self::set_status( $order_id, $status );

		$redirect = wp_get_referer();

		if ( ! $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=' . WFO_CPT_ORDER );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'wfo_marked'      => 1,
					'wfo_mark_status' => $status,
				),
				$redirect
			)
		);
		exit;
	}

	/**
	 * Notices for status updates and bulk actions.
	 */
	public static function notices() {
		if ( ! isset( $_GET['wfo_marked'] ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'edit-' . WFO_CPT_ORDER !== $screen->id ) {
			return;
		}

		$count  = absint( $_GET['wfo_marked'] );
		$status = isset( $_GET['wfo_mark_status'] ) ? sanitize_key( wp_unslash( $_GET['wfo_mark_status'] ) ) : 'new';

		if ( $count < 1 ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'No orders were updated.', 'whatsapp-menu-orders' ) . '</p></div>';
			return;
		}

		if ( 'handled' === $status ) {
			/* translators: %s: number of orders. */
			$message = sprintf(
				_n( '%s order marked as handled.', '%s orders marked as handled.', $count, 'whatsapp-menu-orders' ),
				number_format_i18n( $count )
			);
		} else {
			/* translators: %s: number of orders. */
			$message = sprintf(
				_n( '%s order marked as new.', '%s orders marked as new.', $count, 'whatsapp-menu-orders' ),
				number_format_i18n( $count )
			);
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Export button above the orders list.
	 *
	 * @param string $post_type Current post type.
	 */
	public static function export_button( $post_type ) {
		if ( WFO_CPT_ORDER !== $post_type || ! current_user_can( Capabilities::MANAGE_ORDERS ) ) {
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wfo_export_orders' ),
			'wfo_export_orders'
		);

		printf(
			'<a class="button wfo-export-button" href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Export CSV', 'whatsapp-menu-orders' )
		);
	}

	/**
	 * Stream all orders as CSV.
	 */
	public static function export_csv() {
		if ( ! current_user_can( Capabilities::MANAGE_ORDERS ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export orders.', 'whatsapp-menu-orders' ) );
		}

		check_admin_referer( 'wfo_export_orders' );

		$orders = get_posts(
			array(
				'post_type'           => WFO_CPT_ORDER,
				'post_status'         => 'any',
				'posts_per_page'      => -1,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);

		$filename = 'wfo-orders-' . gmdate( 'Y-m-d-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			exit;
		}

		fputcsv(
			$output,
			array(
				__( 'Code', 'whatsapp-menu-orders' ),
				__( 'Created', 'whatsapp-menu-orders' ),
				__( 'Status', 'whatsapp-menu-orders' ),
				__( 'Type', 'whatsapp-menu-orders' ),
				__( 'Payment', 'whatsapp-menu-orders' ),
				__( 'Name', 'whatsapp-menu-orders' ),
				__( 'Phone', 'whatsapp-menu-orders' ),
				__( 'Address', 'whatsapp-menu-orders' ),
				__( 'Items', 'whatsapp-menu-orders' ),
				__( 'Subtotal', 'whatsapp-menu-orders' ),
				__( 'Delivery', 'whatsapp-menu-orders' ),
				__( 'Total', 'whatsapp-menu-orders' ),
				__( 'Note', 'whatsapp-menu-orders' ),
				__( 'Message', 'whatsapp-menu-orders' ),
			)
		);

		foreach ( $orders as $order_post ) {
			$data = self::get_order_data( $order_post->ID );

			if ( ! $data ) {
				continue;
			}

			$status = self::status( $data['status'] );

			fputcsv(
				$output,
				array(
					self::csv_safe( $data['code'] ),
					self::csv_safe( mysql2date( 'Y-m-d H:i', $order_post->post_date ) ),
					self::csv_safe( $status['label'] ),
					self::csv_safe( self::type_label( $data['type'] ) ),
					self::csv_safe( self::payment_label( $data['payment'] ) ),
					self::csv_safe( $data['customer']['name'] ),
					self::csv_safe( $data['customer']['phone'] ),
					self::csv_safe( $data['customer']['address'] ),
					self::csv_safe( self::items_text( $data['items'] ) ),
					Money::to_input( (int) $data['totals']['subtotal'] ),
					Money::to_input( (int) $data['totals']['delivery'] ),
					Money::to_input( (int) $data['totals']['total'] ),
					self::csv_safe( $data['customer']['note'] ),
					self::csv_safe( $data['message'] ),
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Keep the retention cron event in sync with the setting.
	 */
	public static function sync_retention() {
		$days      = (int) Settings::get( 'retention_days' );
		$scheduled = wp_next_scheduled( 'wfo_cleanup_orders' );

		if ( $days > 0 ) {
			if ( ! $scheduled ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wfo_cleanup_orders' );
			}

			return;
		}

		if ( $scheduled ) {
			wp_clear_scheduled_hook( 'wfo_cleanup_orders' );
		}
	}

	/**
	 * Permanently delete orders older than the retention window.
	 */
	public static function cleanup_orders() {
		$days = (int) Settings::get( 'retention_days' );

		if ( $days <= 0 ) {
			self::sync_retention();
			return;
		}

		$cutoff = wp_date( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$ids = get_posts(
			array(
				'post_type'      => WFO_CPT_ORDER,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'column' => 'post_date',
						'before' => $cutoff,
					),
				),
			)
		);

		foreach ( $ids as $order_id ) {
			wp_delete_post( (int) $order_id, true );
		}

		/**
		 * Fires after old orders were deleted by the retention routine.
		 *
		 * @param int[] $ids Deleted order ids.
		 */
		do_action( 'wfo_orders_cleaned', $ids );
	}

	/**
	 * Order styles on the order screens.
	 */
	public static function assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || WFO_CPT_ORDER !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'wfo-admin', WFO_URL . 'assets/css/wfo-admin.css', array(), WFO_VERSION );
	}

	/**
	 * Status key and translated label.
	 *
	 * @param string $status Raw status.
	 * @return array{key:string,label:string}
	 */
	public static function status( $status ) {
		if ( 'handled' === $status ) {
			return array(
				'key'   => 'handled',
				'label' => __( 'Handled', 'whatsapp-menu-orders' ),
			);
		}

		return array(
			'key'   => 'new',
			'label' => __( 'New', 'whatsapp-menu-orders' ),
		);
	}

	/**
	 * Translated order type.
	 *
	 * @param string $type pickup|delivery.
	 * @return string
	 */
	public static function type_label( $type ) {
		if ( 'pickup' === $type ) {
			return __( 'Pickup', 'whatsapp-menu-orders' );
		}

		if ( 'delivery' === $type ) {
			return __( 'Delivery', 'whatsapp-menu-orders' );
		}

		return __( '—', 'whatsapp-menu-orders' );
	}

	/**
	 * Translated payment method.
	 *
	 * @param string $payment cash|bank.
	 * @return string
	 */
	public static function payment_label( $payment ) {
		if ( 'cash' === $payment ) {
			return __( 'Cash', 'whatsapp-menu-orders' );
		}

		if ( 'bank' === $payment ) {
			return __( 'Bank transfer', 'whatsapp-menu-orders' );
		}

		return __( '—', 'whatsapp-menu-orders' );
	}

	/**
	 * Read and normalize an order.
	 *
	 * Uses WFO\Order::get() when the domain layer is loaded, and falls back to the
	 * stored meta keys, which are the source of truth either way.
	 *
	 * @param int $post_id Order id.
	 * @return array|null
	 */
	public static function get_order_data( $post_id ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );

		if ( ! $post || WFO_CPT_ORDER !== $post->post_type ) {
			return null;
		}

		$meta = array(
			'code'     => (string) get_post_meta( $post_id, '_wfo_code', true ),
			'items'    => get_post_meta( $post_id, '_wfo_items', true ),
			'customer' => get_post_meta( $post_id, '_wfo_customer', true ),
			'totals'   => get_post_meta( $post_id, '_wfo_totals', true ),
			'message'  => (string) get_post_meta( $post_id, '_wfo_message', true ),
			'payment'  => (string) get_post_meta( $post_id, '_wfo_payment', true ),
			'type'     => (string) get_post_meta( $post_id, '_wfo_type', true ),
			'status'   => (string) get_post_meta( $post_id, '_wfo_status', true ),
		);

		if ( class_exists( '\WFO\Order' ) && method_exists( '\WFO\Order', 'get' ) ) {
			$order = \WFO\Order::get( $post_id );

			if ( is_array( $order ) ) {
				if ( isset( $order['order'] ) && is_array( $order['order'] ) ) {
					$order = $order['order'];
				}

				foreach ( array_keys( $meta ) as $key ) {
					$value = self::first( $order, array( $key, '_wfo_' . $key ) );

					if ( null !== $value ) {
						$meta[ $key ] = $value;
					}
				}

				if ( is_array( $meta['customer'] ) ) {
					foreach ( array( 'name', 'phone', 'address', 'time', 'note' ) as $key ) {
						if ( ! empty( $meta['customer'][ $key ] ) ) {
							continue;
						}

						$value = self::first( $order, array( $key ) );

						if ( null !== $value && is_scalar( $value ) && '' !== (string) $value ) {
							$meta['customer'][ $key ] = (string) $value;
						}
					}
				}
			}
		}

		$customer_raw = is_array( $meta['customer'] ) ? $meta['customer'] : array();
		$customer     = array();

		foreach ( array( 'name', 'phone', 'address', 'time', 'note' ) as $key ) {
			$customer[ $key ] = isset( $customer_raw[ $key ] ) && is_scalar( $customer_raw[ $key ] ) ? (string) $customer_raw[ $key ] : '';
		}

		$totals_raw = is_array( $meta['totals'] ) ? $meta['totals'] : array();
		$subtotal   = (int) self::first( $totals_raw, array( 'subtotal', 'sub_total', 'subTotal' ), 0 );
		$delivery   = (int) self::first( $totals_raw, array( 'delivery', 'delivery_fee', 'deliveryFee' ), 0 );
		$total      = (int) self::first( $totals_raw, array( 'total', 'grand_total', 'grandTotal' ), 0 );

		$lines    = self::normalize_items( $meta['items'] );
		$line_sum = 0;
		$has_line = false;

		foreach ( $lines as $line ) {
			if ( null !== $line['total'] ) {
				$line_sum += (int) $line['total'];
				$has_line  = true;
			}
		}

		if ( $subtotal < 1 && $has_line ) {
			$subtotal = $line_sum;
		}

		if ( $total < 1 ) {
			$total = $subtotal + $delivery;
		}

		$code = trim( (string) $meta['code'] );

		if ( '' === $code ) {
			$code = '' !== trim( (string) $post->post_title ) ? (string) $post->post_title : '#' . $post_id;
		}

		$payment = strtolower( trim( (string) $meta['payment'] ) );
		$type    = strtolower( trim( (string) $meta['type'] ) );

		return array(
			'id'       => $post_id,
			'code'     => $code,
			'created'  => (string) $post->post_date,
			'status'   => ( 'handled' === $meta['status'] ) ? 'handled' : 'new',
			'payment'  => in_array( $payment, array( 'cash', 'bank' ), true ) ? $payment : '',
			'type'     => in_array( $type, array( 'pickup', 'delivery' ), true ) ? $type : '',
			'customer' => $customer,
			'items'    => $lines,
			'totals'   => array(
				'subtotal' => max( 0, $subtotal ),
				'delivery' => max( 0, $delivery ),
				'total'    => max( 0, $total ),
			),
			'message'  => (string) $meta['message'],
		);
	}

	/**
	 * Normalize the stored order items.
	 *
	 * Reads the canonical line shape (id, name, qty, price, opts, note, total) and
	 * tolerates the common aliases used while the domain layer computes an order.
	 *
	 * @param mixed $raw Stored items.
	 * @return array
	 */
	private static function normalize_items( $raw ) {
		$lines = array();

		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! is_array( $raw ) ) {
			return $lines;
		}

		foreach ( $raw as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$item_id = isset( $entry['id'] ) ? absint( $entry['id'] ) : 0;
			$qty     = isset( $entry['qty'] ) ? (int) $entry['qty'] : ( isset( $entry['quantity'] ) ? (int) $entry['quantity'] : 1 );
			$qty     = max( 1, $qty );

			$name = '';

			foreach ( array( 'name', 'title', 'label', 'item_name' ) as $key ) {
				if ( isset( $entry[ $key ] ) && is_scalar( $entry[ $key ] ) && '' !== trim( (string) $entry[ $key ] ) ) {
					$name = (string) $entry[ $key ];
					break;
				}
			}

			if ( '' === $name && $item_id ) {
				$name = (string) get_the_title( $item_id );
			}

			if ( '' === $name ) {
				$name = $item_id ? '#' . $item_id : __( 'Unknown item', 'whatsapp-menu-orders' );
			}

			$unit_price = null;

			foreach ( array( 'price', 'unit_price', 'unitPrice' ) as $key ) {
				if ( isset( $entry[ $key ] ) && is_numeric( $entry[ $key ] ) ) {
					$unit_price = (int) $entry[ $key ];
					break;
				}
			}

			$line_total = null;

			foreach ( array( 'total', 'line_total', 'lineTotal', 'subtotal' ) as $key ) {
				if ( isset( $entry[ $key ] ) && is_numeric( $entry[ $key ] ) ) {
					$line_total = (int) $entry[ $key ];
					break;
				}
			}

			if ( null === $line_total && null !== $unit_price ) {
				$line_total = $unit_price * $qty;
			}

			$options = array();

			foreach ( array( 'opts', 'options', 'extras', 'addons' ) as $key ) {
				if ( isset( $entry[ $key ] ) && is_array( $entry[ $key ] ) ) {
					$options = self::normalize_options( $entry[ $key ], $item_id );
					break;
				}
			}

			$note = '';

			foreach ( array( 'note', 'notes', 'comment' ) as $key ) {
				if ( isset( $entry[ $key ] ) && is_scalar( $entry[ $key ] ) ) {
					$note = (string) $entry[ $key ];
					break;
				}
			}

			$lines[] = array(
				'name'    => $name,
				'qty'     => $qty,
				'options' => $options,
				'note'    => $note,
				'total'   => $line_total,
			);
		}

		return $lines;
	}

	/**
	 * Normalize option selections for one line.
	 *
	 * @param array $raw     Stored option selections.
	 * @param int   $item_id Menu item id, used to resolve "g1:o2" ids to labels.
	 * @return array<int,array{label:string,price:int|null}>
	 */
	private static function normalize_options( $raw, $item_id ) {
		$map = self::option_map( $item_id );
		$out = array();

		foreach ( $raw as $option ) {
			$label = '';
			$price = null;

			if ( is_string( $option ) || is_numeric( $option ) ) {
				$key = trim( (string) $option );

				if ( isset( $map[ $key ] ) ) {
					$label = $map[ $key ]['label'];
					$price = $map[ $key ]['price'];
				} else {
					$label = $key;
				}
			} elseif ( is_array( $option ) ) {
				foreach ( array( 'label', 'name', 'title' ) as $key ) {
					if ( isset( $option[ $key ] ) && is_scalar( $option[ $key ] ) && '' !== trim( (string) $option[ $key ] ) ) {
						$label = (string) $option[ $key ];
						break;
					}
				}

				foreach ( array( 'price', 'delta', 'amount' ) as $key ) {
					if ( isset( $option[ $key ] ) && is_numeric( $option[ $key ] ) ) {
						$price = (int) $option[ $key ];
						break;
					}
				}

				if ( '' === $label && isset( $option['id'] ) ) {
					$option_id = sanitize_key( (string) $option['id'] );

					if ( isset( $map[ $option_id ] ) ) {
						$label = $map[ $option_id ]['label'];
						$price = null === $price ? $map[ $option_id ]['price'] : $price;
					}
				}
			}

			$label = trim( $label );

			if ( '' === $label ) {
				continue;
			}

			$out[] = array(
				'label' => $label,
				'price' => $price,
			);
		}

		return $out;
	}

	/**
	 * Map of "g1:o2" ids to labels for a menu item.
	 *
	 * @param int $item_id Menu item id.
	 * @return array<string,array{label:string,price:int}>
	 */
	private static function option_map( $item_id ) {
		static $cache = array();

		$item_id = absint( $item_id );

		if ( ! $item_id ) {
			return array();
		}

		if ( isset( $cache[ $item_id ] ) ) {
			return $cache[ $item_id ];
		}

		$map = array();

		if ( class_exists( '\WFO\Menu_Repository' ) && method_exists( '\WFO\Menu_Repository', 'groups' ) ) {
			foreach ( (array) \WFO\Menu_Repository::groups( $item_id ) as $group ) {
				$group_id = isset( $group['id'] ) ? (string) $group['id'] : '';

				if ( ! isset( $group['options'] ) || ! is_array( $group['options'] ) ) {
					continue;
				}

				foreach ( $group['options'] as $option ) {
					$option_id = isset( $option['id'] ) ? (string) $option['id'] : '';

					if ( '' === $option_id || ! isset( $option['label'] ) ) {
						continue;
					}

					$entry = array(
						'label' => (string) $option['label'],
						'price' => isset( $option['price'] ) ? (int) $option['price'] : 0,
					);

					if ( '' !== $group_id ) {
						$map[ $group_id . ':' . $option_id ] = $entry;
					}

					$map[ $option_id ] = $entry;
				}
			}
		}

		$cache[ $item_id ] = $map;

		return $map;
	}

	/**
	 * One line of items for the CSV "items" cell.
	 *
	 * @param array $lines Normalized lines.
	 * @return string
	 */
	public static function items_text( $lines ) {
		$parts = array();

		foreach ( (array) $lines as $line ) {
			$text = $line['qty'] . '× ' . $line['name'];

			if ( $line['options'] ) {
				$options = array();

				foreach ( $line['options'] as $option ) {
					$label = $option['label'];

					if ( null !== $option['price'] && 0 !== (int) $option['price'] ) {
						$label .= ' (' . Money::format( (int) $option['price'] ) . ')';
					}

					$options[] = $label;
				}

				$text .= ' [' . implode( ', ', $options ) . ']';
			}

			if ( '' !== $line['note'] ) {
				$text .= ' — ' . $line['note'];
			}

			if ( null !== $line['total'] ) {
				$text .= ' = ' . Money::format( (int) $line['total'] );
			}

			$parts[] = $text;
		}

		return implode( ' | ', $parts );
	}

	/**
	 * Build a WhatsApp link for a stored message.
	 *
	 * @param string $message Plain text message.
	 * @return string
	 */
	private static function wa_url( $message ) {
		if ( '' === trim( (string) $message ) ) {
			return '';
		}

		if ( class_exists( '\WFO\Order' ) && method_exists( '\WFO\Order', 'wa_url' ) ) {
			return (string) \WFO\Order::wa_url( (string) $message );
		}

		$number = preg_replace( '/\D+/', '', (string) Settings::get( 'whatsapp_number' ) );

		if ( '' === (string) $number ) {
			return '';
		}

		return 'https://wa.me/' . $number . '?text=' . rawurlencode( (string) $message );
	}

	/**
	 * Status change through WFO\Order when available, meta otherwise.
	 *
	 * @param int    $post_id Order id.
	 * @param string $status  new|handled.
	 */
	private static function set_status( $post_id, $status ) {
		$post_id = absint( $post_id );
		$status  = 'handled' === $status ? 'handled' : 'new';

		if ( class_exists( '\WFO\Order' ) && method_exists( '\WFO\Order', 'mark' ) ) {
			\WFO\Order::mark( $post_id, $status );
			return;
		}

		update_post_meta( $post_id, '_wfo_status', $status );
	}

	/**
	 * Human readable order date from the site's formats.
	 *
	 * @param int $post_id Order id.
	 * @return string
	 */
	private static function date_label( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return '';
		}

		return get_the_date( (string) get_option( 'date_format' ), $post ) . ' · ' . get_the_time( (string) get_option( 'time_format' ), $post );
	}

	/**
	 * First present, non-empty value from a list of keys.
	 *
	 * @param mixed $data    Array to read.
	 * @param array $keys    Candidate keys.
	 * @param mixed $default Fallback.
	 * @return mixed
	 */
	private static function first( $data, $keys, $default = null ) {
		if ( ! is_array( $data ) ) {
			return $default;
		}

		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			if ( null === $data[ $key ] || '' === $data[ $key ] ) {
				continue;
			}

			return $data[ $key ];
		}

		return $default;
	}

	/**
	 * Prevent spreadsheet formula injection in exported text cells.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private static function csv_safe( $value ) {
		$value = (string) $value;

		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}

		return $value;
	}
}
