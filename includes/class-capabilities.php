<?php
/**
 * Capability mapping for customer orders.
 *
 * Menu items are editable by anyone who can write a post, but orders contain
 * customer names, phones and addresses. They use a dedicated capability so the
 * order screens, CSV export and dashboard widget stay with administrators.
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the order family of capabilities onto manage_options.
 */
class Capabilities {

	/**
	 * Capability that grants access to customer orders.
	 */
	const MANAGE_ORDERS = 'manage_wfo_orders';

	/**
	 * Option that records which capability set has been granted.
	 */
	const VERSION_OPTION = 'wfo_caps_version';

	/**
	 * Current capability set version.
	 */
	const VERSION = '1';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'map_meta_cap', array( __CLASS__, 'map' ), 10, 4 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_grant' ) );
	}

	/**
	 * Every capability the order screens use.
	 *
	 * WordPress checks primitive capabilities against `allcaps` directly, so
	 * map_meta_cap() alone is not enough: the administrator role has to own them.
	 *
	 * @return string[]
	 */
	public static function caps() {
		return array(
			self::MANAGE_ORDERS,
			'edit_wfo_order',
			'read_wfo_order',
			'delete_wfo_order',
			'edit_wfo_orders',
			'edit_others_wfo_orders',
			'edit_published_wfo_orders',
			'edit_private_wfo_orders',
			'publish_wfo_orders',
			'read_private_wfo_orders',
			'delete_wfo_orders',
			'delete_others_wfo_orders',
			'delete_published_wfo_orders',
			'delete_private_wfo_orders',
		);
	}

	/**
	 * Grant the capabilities once (activation, or the first admin request after an update).
	 */
	public static function grant() {
		$role = get_role( 'administrator' );

		if ( $role ) {
			foreach ( self::caps() as $cap ) {
				$role->add_cap( $cap );
			}
		}

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/**
	 * Remove the capabilities again when the plugin is deactivated.
	 */
	public static function revoke() {
		$role = get_role( 'administrator' );

		if ( $role ) {
			foreach ( self::caps() as $cap ) {
				$role->remove_cap( $cap );
			}
		}

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Grant on existing installs that never ran the new activation code.
	 */
	public static function maybe_grant() {
		if ( self::VERSION === (string) get_option( self::VERSION_OPTION, '' ) ) {
			return;
		}

		self::grant();
	}

	/**
	 * Rewrite every order capability to manage_options.
	 *
	 * The filter runs for primitive capabilities too, so the post type can use
	 * its own capability names without granting them to any role.
	 *
	 * @param string[] $caps    Required primitive capabilities.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id User id.
	 * @param array    $args    Extra arguments (post id, author id…).
	 * @return string[]
	 */
	public static function map( $caps, $cap, $user_id, $args ) {
		unset( $user_id, $args );

		if ( self::MANAGE_ORDERS === $cap ) {
			return array( 'manage_options' );
		}

		return $caps;
	}

	/**
	 * Can the current user read customer orders?
	 *
	 * @return bool
	 */
	public static function can_manage_orders() {
		return current_user_can( self::MANAGE_ORDERS );
	}
}
