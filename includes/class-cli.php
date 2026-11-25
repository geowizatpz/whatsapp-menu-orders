<?php
/**
 * WP-CLI commands for the order pipeline.
 *
 * Loaded only when WP-CLI is running; the class also guards itself so it can
 * be autoloaded safely from a normal request.
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * `wp wfo` commands.
 */
class CLI {

	/**
	 * Register the command tree.
	 */
	public static function init() {
		if ( ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'wfo', __CLASS__ );
	}

	/**
	 * Shows the configuration and health of the order pipeline.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wfo status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( $args, $assoc_args ) {
		unset( $args, $assoc_args );

		$settings = Settings::all();
		$number   = WhatsApp::normalize(
			isset( $settings['whatsapp_number'] ) ? (string) $settings['whatsapp_number'] : '',
			isset( $settings['country_code'] ) ? (string) $settings['country_code'] : ''
		);

		if ( '' === $number ) {
			\WP_CLI::line( __( 'WhatsApp number: not configured', 'whatsapp-menu-orders' ) );
		} else {
			\WP_CLI::line(
				sprintf(
					/* translators: %s: normalized WhatsApp number, e.g. +525512345678. */
					__( 'WhatsApp number: +%s', 'whatsapp-menu-orders' ),
					$number
				)
			);
		}

		$currencies = Money::currencies();
		$currency   = isset( $settings['currency'] ) ? (string) $settings['currency'] : 'USD';
		$label      = isset( $currencies[ $currency ] ) ? $currencies[ $currency ]['label'] : $currency;

		\WP_CLI::line(
			sprintf(
				/* translators: 1: currency code, 2: currency label. */
				__( 'Currency: %1$s (%2$s)', 'whatsapp-menu-orders' ),
				$currency,
				$label
			)
		);

		$published = get_posts(
			array(
				'post_type'      => WFO_CPT_ITEM,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);

		$available = count( Menu_Repository::items() );

		\WP_CLI::line(
			sprintf(
				/* translators: 1: number of published items, 2: number of items available for ordering. */
				_n( '%1$d item (%2$d available)', '%1$d items (%2$d available)', count( $published ), 'whatsapp-menu-orders' ),
				count( $published ),
				$available
			)
		);

		$hours = Hours::state();

		\WP_CLI::line(
			sprintf(
				/* translators: %s: opening state label, e.g. "Open now · closes 22:00". */
				__( 'Now: %s', 'whatsapp-menu-orders' ),
				$hours['label']
			)
		);

		$last = get_posts(
			array(
				'post_type'      => WFO_CPT_ORDER,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( empty( $last ) ) {
			\WP_CLI::line( __( 'Last order: none', 'whatsapp-menu-orders' ) );
		} else {
			$order = Order::get( (int) $last[0] );

			if ( ! $order ) {
				\WP_CLI::line( __( 'Last order: unreadable', 'whatsapp-menu-orders' ) );
			} else {
				\WP_CLI::line(
					sprintf(
						/* translators: 1: order code, 2: creation date, 3: order total. */
						__( 'Last order: %1$s · %2$s · %3$s', 'whatsapp-menu-orders' ),
						$order['code'],
						$order['created'],
						Money::format( (int) $order['totals']['total'], self::money_settings( $order ) )
					)
				);
			}
		}

		\WP_CLI::success( __( 'Order pipeline loaded.', 'whatsapp-menu-orders' ) );
	}

	/**
	 * Lists recent orders.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Filter by order status: new, handled or all.
	 * ---
	 * default: new
	 * ---
	 *
	 * [--limit=<number>]
	 * : Maximum number of orders to show.
	 * ---
	 * default: 20
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wfo orders --status=handled --limit=50
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function orders( $args, $assoc_args ) {
		unset( $args );

		$status = isset( $assoc_args['status'] ) ? sanitize_key( (string) $assoc_args['status'] ) : 'new';
		$limit  = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 20;
		$limit  = max( 1, min( 200, $limit ) );

		if ( 'all' !== $status && ! in_array( $status, Order::STATUSES, true ) ) {
			\WP_CLI::error( __( 'Unknown status. Use new, handled or all.', 'whatsapp-menu-orders' ) );
		}

		$query = array(
			'post_type'      => WFO_CPT_ORDER,
			'post_status'    => 'any',
			'posts_per_page' => $limit,
			'no_found_rows'  => true,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( 'all' !== $status ) {
			$query['meta_query'] = array(
				array(
					'key'   => '_wfo_status',
					'value' => $status,
				),
			);
		}

		$posts  = get_posts( $query );
		$rows   = array();
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		$labels = array(
			'code'    => __( 'Code', 'whatsapp-menu-orders' ),
			'created' => __( 'Created', 'whatsapp-menu-orders' ),
			'name'    => __( 'Customer', 'whatsapp-menu-orders' ),
			'total'   => __( 'Total', 'whatsapp-menu-orders' ),
			'status'  => __( 'Status', 'whatsapp-menu-orders' ),
		);

		foreach ( $posts as $post ) {
			$order = Order::get( (int) $post->ID );

			if ( ! $order ) {
				continue;
			}

			$timestamp = strtotime( $order['created'] );
			$created   = $timestamp ? wp_date( $format, $timestamp ) : $order['created'];

			$rows[] = array(
				$labels['code']    => $order['code'],
				$labels['created'] => $created,
				$labels['name']    => $order['customer']['name'],
				$labels['total']   => Money::format( (int) $order['totals']['total'], self::money_settings( $order ) ),
				$labels['status']  => $order['status'],
			);
		}

		if ( empty( $rows ) ) {
			\WP_CLI::line( __( 'No orders found.', 'whatsapp-menu-orders' ) );
			return;
		}

		self::render_table( $rows, array_values( $labels ) );
	}

	/**
	 * Deletes orders older than a number of days.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Age in days; orders older than this are deleted permanently.
	 * ---
	 * default: 30
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wfo prune --days=90
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function prune( $args, $assoc_args ) {
		unset( $args );

		$days = isset( $assoc_args['days'] ) ? (int) $assoc_args['days'] : 30;

		if ( $days < 1 ) {
			\WP_CLI::error( __( 'Please pass a positive number of days.', 'whatsapp-menu-orders' ) );
		}

		$count = Order::prune( $days );

		\WP_CLI::success(
			sprintf(
				/* translators: %d: number of deleted orders. */
				_n( '%d order deleted.', '%d orders deleted.', $count, 'whatsapp-menu-orders' ),
				$count
			)
		);
	}

	/**
	 * Render a table.
	 *
	 * WP_CLI has no WP_CLI::table() method; table output is provided by
	 * WP_CLI\Utils\format_items(). A plain-text fallback keeps the command
	 * usable if that internal helper ever moves.
	 *
	 * @param array $rows    Rows keyed by their translated header label.
	 * @param array $headers Translated header labels.
	 */
	private static function render_table( $rows, $headers ) {
		if ( function_exists( '\WP_CLI\Utils\format_items' ) ) {
			\WP_CLI\Utils\format_items( 'table', $rows, $headers );
			return;
		}

		\WP_CLI::line( implode( "\t", $headers ) );

		foreach ( $rows as $row ) {
			\WP_CLI::line( implode( "\t", array_values( $row ) ) );
		}
	}

	/**
	 * Settings array that formats money in the order's own currency.
	 *
	 * @param array $order Order shape from Order::get().
	 * @return array
	 */
	private static function money_settings( $order ) {
		$settings = Settings::all();

		if ( ! empty( $order['totals']['currency'] ) ) {
			$settings['currency'] = (string) $order['totals']['currency'];
		}

		return $settings;
	}
}
