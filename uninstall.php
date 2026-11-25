<?php
/**
 * Uninstall handler.
 *
 * Settings and presets are removed. Menu items and orders are kept on purpose:
 * deleting a plugin should never destroy a restaurant's order history.
 *
 * @package WFO
 */

defined( 'ABSPATH' ) || exit;

delete_option( 'wfo_settings' );
delete_option( 'wfo_extra_presets' );
delete_transient( 'wfo_order_throttle' );

/*
 * Orders contain personal data (name, phone, address). They are kept by default
 * so uninstalling the plugin never destroys a restaurant's records; a site that
 * wants them gone can opt in:
 *
 *     add_filter( 'wfo_delete_orders_on_uninstall', '__return_true' );
 */
if ( apply_filters( 'wfo_delete_orders_on_uninstall', false ) ) {
	$wfo_orders = get_posts(
		array(
			'post_type'        => 'wfo_order',
			'post_status'      => 'any',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);

	foreach ( $wfo_orders as $wfo_order_id ) {
		wp_delete_post( (int) $wfo_order_id, true );
	}
}
