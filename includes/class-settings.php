<?php
/**
 * Plugin settings: schema, defaults, sanitizing and access.
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for the wfo_settings option.
 */
class Settings {

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'update_option_' . WFO_OPTION, array( __CLASS__, 'flush_cache' ), 10, 0 );
		add_action( 'add_option_' . WFO_OPTION, array( __CLASS__, 'flush_cache' ), 10, 0 );
	}

	/**
	 * Reset the in-request cache.
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Default message template.
	 *
	 * @return string
	 */
	public static function default_template() {
		return "*New order* {code}\n"
			. "————————————\n"
			. "{items}\n"
			. "————————————\n"
			. 'Subtotal: {subtotal}' . "\n"
			. 'Delivery: {delivery}' . "\n"
			. "*Total: {total}*\n"
			. 'Payment: {payment}' . "\n"
			. 'Type: {type}' . "\n"
			. 'Name: {name}' . "\n"
			. 'Phone: {phone}' . "\n"
			. "{address}\n"
			. "{time}\n"
			. '{note}';
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'restaurant_name'       => '',
			'ui_language'           => 'site',
			'currency'              => 'USD',
			'currency_position'     => 'left',
			'decimals'              => 2,
			'thousands_sep'         => ',',
			'decimal_sep'           => '.',
			'enable_pickup'         => 1,
			'enable_delivery'       => 1,
			'delivery_fee'          => 0,
			'free_delivery_over'    => 0,
			'min_order'             => 0,
			'min_order_scope'       => 'subtotal',
			'delivery_note'         => '',
			'delivery_fee_label'    => '',
			'default_order_type'    => 'pickup',
			'google_maps_key'       => '',
			'map_center'            => '',
			'opening_hours_enabled' => 0,
			'opening_hours'         => array(),
			'closed_message'        => '',
			'payment_cash'          => 1,
			'payment_bank'          => 1,
			'bank_details'          => '',
			'bank_note'             => '',
			'payment_note'          => '',
			'whatsapp_number'       => '',
			'whatsapp_number_2'     => '',
			'country_code'          => '52',
			'message_template'      => self::default_template(),
			'order_prefix'          => 'WFO',
			'save_orders'           => 1,
			'notify_email'          => '',
			'retention_days'        => 365,
			'accent'                => '#e2572b',
			'radius'                => 14,
			'dark_mode'             => 'auto',
			'custom_css'            => '',
			'show_search'           => 1,
			'menu_page_id'          => 0,
		);
	}

	/**
	 * All settings, defaults merged in.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( WFO_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		self::$cache = array_merge( self::defaults(), $stored );

		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key is unknown.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		return $default;
	}

	/**
	 * Merge and save a partial update.
	 *
	 * @param array $partial Settings to change.
	 */
	public static function update( array $partial ) {
		$current = self::all();
		$merged  = array_merge( $current, $partial );
		$clean   = self::sanitize( $merged );

		/*
		 * sanitize() reads every money field as human input ("35" -> 3500), so
		 * re-sanitizing the already-stored minor units would multiply them on
		 * every partial update. Only the keys the caller actually sent are
		 * sanitized; everything else keeps its stored value.
		 */
		foreach ( $current as $key => $value ) {
			if ( ! array_key_exists( $key, $partial ) ) {
				$clean[ $key ] = $value;
			}
		}

		update_option( WFO_OPTION, $clean );
		self::$cache = null;

		return $clean;
	}

	/**
	 * Sanitize a complete settings array.
	 *
	 * @param array $raw Raw values.
	 * @return array
	 */
	public static function sanitize( array $raw ) {
		$defaults = self::defaults();
		$out      = array();
		$currencies = Money::currencies();

		$text_keys = array(
			'restaurant_name',
			'thousands_sep',
			'decimal_sep',
			'closed_message',
			'delivery_fee_label',
			'payment_note',
			'notify_email',
		);

		foreach ( $text_keys as $key ) {
			$value = isset( $raw[ $key ] ) ? $raw[ $key ] : $defaults[ $key ];
			$out[ $key ] = 'notify_email' === $key ? sanitize_email( (string) $value ) : sanitize_text_field( (string) $value );
		}

		$out['ui_language'] = in_array( isset( $raw['ui_language'] ) ? $raw['ui_language'] : '', array( 'site', 'en_US', 'es_ES' ), true )
			? $raw['ui_language']
			: 'site';

		// Keep the exact whitelisted code: sanitize_key() would lowercase it.
		$out['currency'] = ( isset( $raw['currency'] ) && isset( $currencies[ $raw['currency'] ] ) )
			? (string) $raw['currency']
			: 'USD';

		$out['currency_position'] = in_array( isset( $raw['currency_position'] ) ? $raw['currency_position'] : '', array( 'left', 'right' ), true )
			? $raw['currency_position']
			: 'left';

		$decimals = isset( $raw['decimals'] ) ? (int) $raw['decimals'] : 2;
		$out['decimals'] = max( 0, min( 2, $decimals ) );

		foreach ( array( 'enable_pickup', 'enable_delivery', 'opening_hours_enabled', 'payment_cash', 'payment_bank', 'save_orders', 'show_search' ) as $key ) {
			$out[ $key ] = empty( $raw[ $key ] ) ? 0 : 1;
		}

		foreach ( array( 'delivery_fee', 'free_delivery_over', 'min_order' ) as $key ) {
			$out[ $key ] = isset( $raw[ $key ] ) ? Money::to_minor( $raw[ $key ] ) : 0;
		}

		$out['min_order_scope'] = ( isset( $raw['min_order_scope'] ) && 'total' === $raw['min_order_scope'] ) ? 'total' : 'subtotal';

		$out['default_order_type'] = ( isset( $raw['default_order_type'] ) && 'delivery' === $raw['default_order_type'] ) ? 'delivery' : 'pickup';

		// Browser keys for the Maps JavaScript API are public by design; keep only the characters Google uses.
		$out['google_maps_key'] = isset( $raw['google_maps_key'] ) ? substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $raw['google_maps_key'] ), 0, 80 ) : '';
		$out['map_center']      = self::sanitize_latlng( isset( $raw['map_center'] ) ? $raw['map_center'] : '' );

		$out['whatsapp_number']   = self::digits( isset( $raw['whatsapp_number'] ) ? $raw['whatsapp_number'] : '' );
		$out['whatsapp_number_2'] = self::digits( isset( $raw['whatsapp_number_2'] ) ? $raw['whatsapp_number_2'] : '' );
		$out['country_code']      = self::digits( isset( $raw['country_code'] ) ? $raw['country_code'] : '52' );

		if ( '' === $out['country_code'] ) {
			$out['country_code'] = '52';
		}

		$prefix = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', isset( $raw['order_prefix'] ) ? $raw['order_prefix'] : 'WFO' ) );
		$out['order_prefix'] = '' !== $prefix ? substr( $prefix, 0, 8 ) : 'WFO';

		$out['retention_days'] = isset( $raw['retention_days'] ) ? max( 0, min( 3650, (int) $raw['retention_days'] ) ) : 0;

		$template = isset( $raw['message_template'] ) ? (string) $raw['message_template'] : $defaults['message_template'];
		$template = sanitize_textarea_field( $template );
		$out['message_template'] = '' !== trim( $template ) ? $template : $defaults['message_template'];

		foreach ( array( 'bank_details', 'bank_note', 'delivery_note' ) as $key ) {
			$out[ $key ] = isset( $raw[ $key ] ) ? sanitize_textarea_field( (string) $raw[ $key ] ) : '';
		}

		$accent          = isset( $raw['accent'] ) ? sanitize_hex_color( (string) $raw['accent'] ) : '';
		$out['accent']   = $accent ? $accent : $defaults['accent'];
		$out['radius']   = isset( $raw['radius'] ) ? max( 0, min( 40, (int) $raw['radius'] ) ) : (int) $defaults['radius'];
		$out['dark_mode'] = in_array( isset( $raw['dark_mode'] ) ? $raw['dark_mode'] : '', array( 'auto', 'light', 'dark' ), true )
			? $raw['dark_mode']
			: 'auto';

		$out['custom_css']   = isset( $raw['custom_css'] ) ? trim( wp_strip_all_tags( (string) $raw['custom_css'] ) ) : '';
		$out['menu_page_id'] = isset( $raw['menu_page_id'] ) ? absint( $raw['menu_page_id'] ) : 0;
		$out['opening_hours'] = self::sanitize_hours( isset( $raw['opening_hours'] ) ? $raw['opening_hours'] : array() );

		/**
		 * Filter the sanitized settings before saving.
		 *
		 * @param array $out Sanitized settings.
		 * @param array $raw Raw input.
		 */
		return apply_filters( 'wfo_sanitize_settings', $out, $raw );
	}

	/**
	 * Normalize a "lat,lng" pair; empty string when invalid.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_latlng( $value ) {
		if ( ! preg_match( '/^\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', (string) $value, $m ) ) {
			return '';
		}

		$lat = (float) $m[1];
		$lng = (float) $m[2];

		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
			return '';
		}

		return sprintf( '%.6f,%.6f', $lat, $lng );
	}

	/**
	 * Keep only digits.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function digits( $value ) {
		return preg_replace( '/\D+/', '', (string) $value );
	}

	/**
	 * Normalize the opening hours structure.
	 *
	 * @param mixed $hours Raw hours.
	 * @return array
	 */
	public static function sanitize_hours( $hours ) {
		$days = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
		$out  = array();

		if ( ! is_array( $hours ) ) {
			return $out;
		}

		foreach ( $days as $day ) {
			$ranges = isset( $hours[ $day ] ) && is_array( $hours[ $day ] ) ? $hours[ $day ] : array();
			$clean  = array();

			foreach ( $ranges as $range ) {
				$open  = isset( $range['open'] ) ? self::sanitize_time( $range['open'] ) : '';
				$close = isset( $range['close'] ) ? self::sanitize_time( $range['close'] ) : '';

				if ( '' === $open || '' === $close || $open === $close ) {
					continue;
				}

				$clean[] = array(
					'open'  => $open,
					'close' => $close,
				);
			}

			if ( $clean ) {
				$out[ $day ] = $clean;
			}
		}

		return $out;
	}

	/**
	 * Validate an H:i time string.
	 *
	 * @param string $value Raw value.
	 * @return string Empty string when invalid.
	 */
	private static function sanitize_time( $value ) {
		$value = trim( (string) $value );

		if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $matches ) ) {
			return '';
		}

		return sprintf( '%02d:%02d', (int) $matches[1], (int) $matches[2] );
	}
}
