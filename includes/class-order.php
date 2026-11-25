<?php
/**
 * Order creation, validation, server-side re-pricing and storage.
 *
 * The browser builds the WhatsApp message and opens the chat; this class is
 * the authoritative side of the order. Every price, label and total is read
 * from the database and recomputed, never taken from the request.
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * Order domain logic.
 */
class Order {

	/**
	 * Order statuses an order may be moved to.
	 */
	const STATUSES = array( 'new', 'handled' );

	/**
	 * Maximum number of distinct lines in one order.
	 */
	const MAX_ITEMS = 50;

	/**
	 * Maximum number of selected options per line.
	 */
	const MAX_OPTIONS = 50;

	/**
	 * Maximum item note length, in characters.
	 */
	const MAX_ITEM_NOTE = 200;

	/**
	 * Maximum order note length, in characters.
	 */
	const MAX_NOTE = 400;

	/**
	 * Maximum WhatsApp message length, in characters.
	 */
	const MAX_MESSAGE = 4000;

	/**
	 * Validate, re-price, store and email an order.
	 *
	 * @param array $payload Raw request payload.
	 * @return array|\WP_Error Order summary, or a WP_Error with a machine readable code.
	 */
	public static function create( $payload ) {
		if ( ! is_array( $payload ) ) {
			return self::error( 'wfo_invalid', __( 'The order could not be read.', 'whatsapp-menu-orders' ) );
		}

		$settings = Settings::all();

		// Order code: the browser generates it from the configured prefix.
		$code = strtoupper( trim( sanitize_text_field( self::scalar( self::value( $payload, 'code', '' ) ) ) ) );

		if ( ! preg_match( '/^[A-Z0-9-]{3,20}$/', $code ) ) {
			return self::error( 'wfo_invalid', __( 'The order code is not valid.', 'whatsapp-menu-orders' ) );
		}

		// Items.
		$raw_items = self::value( $payload, 'items', null );

		if ( ! is_array( $raw_items ) || array() === $raw_items ) {
			return self::error( 'wfo_invalid', __( 'Your order is empty.', 'whatsapp-menu-orders' ) );
		}

		if ( count( $raw_items ) > self::MAX_ITEMS ) {
			return self::error( 'wfo_invalid', __( 'There are too many items in one order.', 'whatsapp-menu-orders' ) );
		}

		// Customer details.
		$customer = self::value( $payload, 'customer', array() );

		if ( ! is_array( $customer ) ) {
			$customer = array();
		}

		$name = self::text( self::value( $customer, 'name', '' ) );

		if ( '' === $name ) {
			return self::error( 'wfo_invalid', __( 'Please enter your name.', 'whatsapp-menu-orders' ) );
		}

		if ( self::length( $name ) > 60 ) {
			return self::error( 'wfo_invalid', __( 'Please use a shorter name.', 'whatsapp-menu-orders' ) );
		}

		$phone = preg_replace( '/\D+/', '', self::scalar( self::value( $customer, 'phone', '' ) ) );
		$phone = is_string( $phone ) ? $phone : '';

		if ( strlen( $phone ) < 6 || strlen( $phone ) > 20 ) {
			return self::error( 'wfo_invalid', __( 'Please enter a valid phone number.', 'whatsapp-menu-orders' ) );
		}

		$type = sanitize_key( self::scalar( self::value( $customer, 'type', '' ) ) );

		if ( ! in_array( $type, array( 'pickup', 'delivery' ), true ) ) {
			return self::error( 'wfo_invalid', __( 'Choose pickup or delivery.', 'whatsapp-menu-orders' ) );
		}

		if ( 'pickup' === $type && empty( $settings['enable_pickup'] ) ) {
			return self::error( 'wfo_disabled', __( 'Pickup is not available right now.', 'whatsapp-menu-orders' ), 409 );
		}

		if ( 'delivery' === $type && empty( $settings['enable_delivery'] ) ) {
			return self::error( 'wfo_disabled', __( 'Delivery is not available right now.', 'whatsapp-menu-orders' ), 409 );
		}

		$address = self::textarea( self::value( $customer, 'address', '' ) );

		if ( self::length( $address ) > 300 ) {
			return self::error( 'wfo_invalid', __( 'Please use a shorter address.', 'whatsapp-menu-orders' ) );
		}

		if ( 'delivery' === $type && '' === $address ) {
			return self::error( 'wfo_invalid', __( 'We need an address for delivery.', 'whatsapp-menu-orders' ) );
		}

		$payment = sanitize_key( self::scalar( self::value( $customer, 'payment', self::value( $payload, 'payment', '' ) ) ) );

		if ( ! in_array( $payment, array( 'cash', 'bank' ), true ) ) {
			return self::error( 'wfo_invalid', __( 'Choose how you would like to pay.', 'whatsapp-menu-orders' ) );
		}

		if ( 'cash' === $payment && empty( $settings['payment_cash'] ) ) {
			return self::error( 'wfo_disabled', __( 'Cash payment is not available right now.', 'whatsapp-menu-orders' ), 409 );
		}

		if ( 'bank' === $payment && empty( $settings['payment_bank'] ) ) {
			return self::error( 'wfo_disabled', __( 'Bank transfer is not available right now.', 'whatsapp-menu-orders' ), 409 );
		}

		$note = self::textarea( self::value( $customer, 'note', '' ) );

		if ( self::length( $note ) > self::MAX_NOTE ) {
			return self::error( 'wfo_invalid', __( 'Please shorten the note for the restaurant.', 'whatsapp-menu-orders' ) );
		}

		$time = self::text( self::value( $customer, 'time', '' ) );

		if ( '' === $time ) {
			$time = 'asap';
		}

		if ( self::length( $time ) > 40 ) {
			return self::error( 'wfo_invalid', __( 'Please choose a valid time.', 'whatsapp-menu-orders' ) );
		}

		$message = self::textarea( self::value( $payload, 'message', '' ) );

		if ( self::length( $message ) > self::MAX_MESSAGE ) {
			return self::error( 'wfo_invalid', __( 'The order message is too long.', 'whatsapp-menu-orders' ) );
		}

		$lang = sanitize_key( self::scalar( self::value( $payload, 'lang', '' ) ) );

		if ( '' === $lang || ! preg_match( '/^[a-z]{2}(_[a-z]{2})?$/', $lang ) ) {
			$lang = I18n::is_spanish() ? 'es' : 'en';
		}

		// Re-price every line from the database.
		$rows     = array();
		$subtotal = 0;

		foreach ( array_values( $raw_items ) as $raw_item ) {
			if ( ! is_array( $raw_item ) ) {
				return self::error( 'wfo_invalid', __( 'One of the items in your order could not be read.', 'whatsapp-menu-orders' ) );
			}

			$item_id = isset( $raw_item['id'] ) ? (int) $raw_item['id'] : 0;

			if ( $item_id <= 0 ) {
				return self::error( 'wfo_invalid', __( 'One of the items in your order could not be read.', 'whatsapp-menu-orders' ) );
			}

			$qty = isset( $raw_item['qty'] ) ? (int) $raw_item['qty'] : 0;

			if ( $qty < 1 || $qty > 99 ) {
				return self::error( 'wfo_invalid', __( 'Each item needs a quantity between 1 and 99.', 'whatsapp-menu-orders' ) );
			}

			$item = Menu_Repository::item( $item_id );

			if ( ! $item ) {
				return self::error(
					'wfo_unavailable',
					__( 'One of the items in your order is no longer on the menu.', 'whatsapp-menu-orders' ),
					409
				);
			}

			$item_name = isset( $item['name'] ) ? self::scalar( $item['name'] ) : '';

			if ( empty( $item['available'] ) ) {
				return self::error(
					'wfo_unavailable',
					sprintf(
						/* translators: %s: item name. */
						__( '“%s” is not available right now.', 'whatsapp-menu-orders' ),
						$item_name
					),
					409
				);
			}

			// Normalize the option groups once per line.
			$groups = array();

			if ( isset( $item['groups'] ) && is_array( $item['groups'] ) ) {
				foreach ( $item['groups'] as $group ) {
					if ( ! is_array( $group ) || empty( $group['id'] ) ) {
						continue;
					}

					$options = array();

					if ( isset( $group['options'] ) && is_array( $group['options'] ) ) {
						foreach ( $group['options'] as $option ) {
							if ( ! is_array( $option ) || empty( $option['id'] ) ) {
								continue;
							}

							$options[ (string) $option['id'] ] = $option;
						}
					}

					$groups[ (string) $group['id'] ] = array(
						'group'   => $group,
						'options' => $options,
					);
				}
			}

			$raw_options = isset( $raw_item['opts'] ) ? $raw_item['opts'] : array();

			if ( ! is_array( $raw_options ) ) {
				return self::error( 'wfo_invalid', __( 'One of the selected options could not be read.', 'whatsapp-menu-orders' ) );
			}

			if ( count( $raw_options ) > self::MAX_OPTIONS ) {
				return self::error( 'wfo_invalid', __( 'Too many options were selected.', 'whatsapp-menu-orders' ) );
			}

			$selected    = array();
			$option_rows = array();
			$delta       = 0;

			foreach ( $raw_options as $raw_option ) {
				if ( ! is_string( $raw_option ) ) {
					return self::error( 'wfo_invalid', __( 'One of the selected options could not be read.', 'whatsapp-menu-orders' ) );
				}

				$raw_option = trim( sanitize_text_field( $raw_option ) );

				if ( '' === $raw_option ) {
					continue;
				}

				$separator = strpos( $raw_option, ':' );

				if ( false === $separator ) {
					return self::error( 'wfo_invalid', __( 'One of the selected options could not be read.', 'whatsapp-menu-orders' ) );
				}

				$group_id  = sanitize_key( substr( $raw_option, 0, $separator ) );
				$option_id = sanitize_key( substr( $raw_option, $separator + 1 ) );

				if ( '' === $group_id || '' === $option_id ) {
					return self::error( 'wfo_invalid', __( 'One of the selected options could not be read.', 'whatsapp-menu-orders' ) );
				}

				if ( isset( $selected[ $group_id ] ) && in_array( $option_id, $selected[ $group_id ], true ) ) {
					continue;
				}

				if ( ! isset( $groups[ $group_id ] ) ) {
					return self::error(
						'wfo_unavailable',
						sprintf(
							/* translators: %s: item name. */
							__( 'The options for “%s” changed. Please add it again.', 'whatsapp-menu-orders' ),
							$item_name
						),
						409
					);
				}

				if ( ! isset( $groups[ $group_id ]['options'][ $option_id ] ) ) {
					return self::error(
						'wfo_unavailable',
						sprintf(
							/* translators: %s: item name. */
							__( 'An option for “%s” is no longer available.', 'whatsapp-menu-orders' ),
							$item_name
						),
						409
					);
				}

				$option = $groups[ $group_id ]['options'][ $option_id ];

				if ( ! empty( $option['soldout'] ) ) {
					return self::error(
						'wfo_unavailable',
						sprintf(
							/* translators: %s: option label. */
							__( '“%s” is sold out.', 'whatsapp-menu-orders' ),
							isset( $option['label'] ) ? (string) $option['label'] : $option_id
						),
						409
					);
				}

				if ( ! isset( $selected[ $group_id ] ) ) {
					$selected[ $group_id ] = array();
				}

				$selected[ $group_id ][] = $option_id;

				$option_delta = isset( $option['price'] ) ? (int) $option['price'] : 0;
				$delta       += $option_delta;

				$option_rows[] = array(
					'label' => isset( $option['label'] ) ? (string) $option['label'] : $option_id,
					'price' => $option_delta,
				);
			}

			// Enforce the group rules stored with the item.
			foreach ( $groups as $group_id => $entry ) {
				$group      = $entry['group'];
				$chosen     = isset( $selected[ $group_id ] ) ? count( array_unique( $selected[ $group_id ] ) ) : 0;
				$min        = isset( $group['min'] ) ? max( 0, (int) $group['min'] ) : 0;
				$max        = isset( $group['max'] ) ? max( 0, (int) $group['max'] ) : 0;
				$group_name = isset( $group['label'] ) ? (string) $group['label'] : $group_id;

				if ( ! empty( $group['required'] ) && $min < 1 ) {
					$min = 1;
				}

				if ( $chosen < $min ) {
					return self::error(
						'wfo_invalid',
						sprintf(
							/* translators: %s: option group label, e.g. "Size". */
							__( 'Choose an option for %s.', 'whatsapp-menu-orders' ),
							$group_name
						)
					);
				}

				if ( $max > 0 && $chosen > $max ) {
					return self::error(
						'wfo_invalid',
						sprintf(
							/* translators: %s: option group label, e.g. "Extras". */
							__( 'Too many options selected for %s.', 'whatsapp-menu-orders' ),
							$group_name
						)
					);
				}
			}

			$item_note = self::textarea( isset( $raw_item['note'] ) ? $raw_item['note'] : '' );

			if ( self::length( $item_note ) > self::MAX_ITEM_NOTE ) {
				return self::error( 'wfo_invalid', __( 'Please shorten the special requests.', 'whatsapp-menu-orders' ) );
			}

			$base_price = isset( $item['price'] ) ? (int) $item['price'] : 0;
			$unit       = max( 0, $base_price + $delta );
			$line_total = $unit * $qty;

			$subtotal += $line_total;

			$rows[] = array(
				'id'      => $item_id,
				'name'    => $item_name,
				'qty'     => $qty,
				'unit'    => $unit,
				'options' => $option_rows,
				'note'    => $item_note,
				'total'   => $line_total,
			);
		}

		// Delivery rules.
		$delivery = 0;

		if ( 'delivery' === $type ) {
			$delivery  = max( 0, (int) $settings['delivery_fee'] );
			$free_over = max( 0, (int) $settings['free_delivery_over'] );

			if ( $free_over > 0 && $subtotal >= $free_over ) {
				$delivery = 0;
			}
		}

		$total = $subtotal + $delivery;

		// Minimum order.
		$min_order = max( 0, (int) $settings['min_order'] );

		if ( $min_order > 0 ) {
			$compare = ( 'total' === $settings['min_order_scope'] ) ? $total : $subtotal;

			if ( $compare < $min_order ) {
				return self::error(
					'wfo_min_order',
					sprintf(
						/* translators: %s: formatted minimum order amount. */
						__( 'The minimum order is %s.', 'whatsapp-menu-orders' ),
						Money::format( $min_order, $settings )
					),
					409,
					array(
						'min_order' => $min_order,
						'remaining' => max( 0, $min_order - $compare ),
					)
				);
			}
		}

		$currency = isset( $settings['currency'] ) ? (string) $settings['currency'] : 'USD';

		/*
		 * The order code is the bank-transfer reference, so two orders must never
		 * share one. Collisions are repaired here and the message is rewritten so
		 * the log, the admin link and the customer's copy stay in sync.
		 */
		$requested_code = $code;
		$code           = self::unique_code( $code );

		if ( $code !== $requested_code && '' !== $message ) {
			$message = str_replace( $requested_code, $code, $message );
		}

		$data = array(
			'code'           => $code,
			'items'          => $rows,
			'customer'       => array(
				'name'    => $name,
				'phone'   => $phone,
				'type'    => $type,
				'address' => $address,
				'time'    => $time,
				'note'    => $note,
			),
			'totals'         => array(
				'subtotal' => $subtotal,
				'delivery' => $delivery,
				'total'    => $total,
				'currency' => $currency,
			),
			'message'        => $message,
			'lang'           => $lang,
			'payment'        => $payment,
			'type'           => $type,
			'status'         => 'new',
			'price_mismatch' => null,
			'ip_hash'        => self::ip_hash(),
		);

		// A client-side total is only a hint: keep ours and flag the difference.
		$client_total = self::client_total( $payload );

		if ( null !== $client_total && abs( $client_total - $total ) > 1 ) {
			$data['price_mismatch'] = (int) $client_total;
		}

		$order_id = 0;

		if ( ! empty( $settings['save_orders'] ) ) {
			$order_id = wp_insert_post(
				array(
					'post_type'    => WFO_CPT_ORDER,
					'post_status'  => 'publish',
					'post_title'   => $code . ' · ' . $name,
					'post_content' => '',
				),
				true
			);

			if ( is_wp_error( $order_id ) || ! $order_id ) {
				return self::error( 'wfo_unavailable', __( 'We could not save your order. Please try again.', 'whatsapp-menu-orders' ), 409 );
			}

			$order_id = (int) $order_id;

			self::save_meta( $order_id, $data );
		}

		self::notify( $data, $settings );

		/**
		 * Fires after an order has been validated, re-priced and (optionally) stored.
		 *
		 * @param int   $order_id New order post id, or 0 when saving is disabled.
		 * @param array $data     Normalized order data.
		 */
		do_action( 'wfo_order_created', $order_id, $data );

		return array(
			'ok'       => true,
			'code'     => $code,
			'subtotal' => $subtotal,
			'delivery' => $delivery,
			'total'    => $total,
			'wa_url'   => self::wa_url( $message ),
		);
	}

	/**
	 * Read an order in the shape the admin screens use.
	 *
	 * @param int $id Order post id.
	 * @return array|null Null when the post is missing or is not an order.
	 */
	public static function get( $id ) {
		$id = absint( $id );

		if ( ! $id ) {
			return null;
		}

		$post = get_post( $id );

		if ( ! $post || WFO_CPT_ORDER !== $post->post_type ) {
			return null;
		}

		$items       = get_post_meta( $id, '_wfo_items', true );
		$items_out   = array();
		$total_items = 0;

		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$options = array();

				if ( isset( $item['options'] ) && is_array( $item['options'] ) ) {
					foreach ( $item['options'] as $option ) {
						if ( ! is_array( $option ) ) {
							continue;
						}

						$options[] = array(
							'label' => self::scalar( isset( $option['label'] ) ? $option['label'] : '' ),
							'price' => isset( $option['price'] ) ? (int) $option['price'] : 0,
						);
					}
				}

				$qty         = isset( $item['qty'] ) ? max( 0, (int) $item['qty'] ) : 0;
				$total_items += $qty;

				$items_out[] = array(
					'id'      => isset( $item['id'] ) ? absint( $item['id'] ) : 0,
					'name'    => self::scalar( isset( $item['name'] ) ? $item['name'] : '' ),
					'qty'     => $qty,
					'unit'    => isset( $item['unit'] ) ? (int) $item['unit'] : 0,
					'options' => $options,
					'note'    => self::scalar( isset( $item['note'] ) ? $item['note'] : '' ),
					'total'   => isset( $item['total'] ) ? (int) $item['total'] : 0,
				);
			}
		}

		$customer_raw = get_post_meta( $id, '_wfo_customer', true );
		$customer_raw = is_array( $customer_raw ) ? $customer_raw : array();
		$customer     = array();

		foreach ( array( 'name', 'phone', 'type', 'address', 'time', 'note' ) as $key ) {
			$customer[ $key ] = self::scalar( isset( $customer_raw[ $key ] ) ? $customer_raw[ $key ] : '' );
		}

		$totals_raw = get_post_meta( $id, '_wfo_totals', true );
		$totals_raw = is_array( $totals_raw ) ? $totals_raw : array();
		$currency   = isset( $totals_raw['currency'] ) ? self::scalar( $totals_raw['currency'] ) : '';

		$totals = array(
			'subtotal' => isset( $totals_raw['subtotal'] ) ? (int) $totals_raw['subtotal'] : 0,
			'delivery' => isset( $totals_raw['delivery'] ) ? (int) $totals_raw['delivery'] : 0,
			'total'    => isset( $totals_raw['total'] ) ? (int) $totals_raw['total'] : 0,
			'currency' => '' !== $currency ? $currency : (string) Settings::get( 'currency', 'USD' ),
		);

		$status = self::scalar( get_post_meta( $id, '_wfo_status', true ) );
		$status = in_array( $status, self::STATUSES, true ) ? $status : 'new';

		$order_type = self::scalar( get_post_meta( $id, '_wfo_type', true ) );
		$order_type = in_array( $order_type, array( 'pickup', 'delivery' ), true ) ? $order_type : '';

		$payment = self::scalar( get_post_meta( $id, '_wfo_payment', true ) );
		$payment = in_array( $payment, array( 'cash', 'bank' ), true ) ? $payment : '';

		return array(
			'id'          => $id,
			'code'        => self::scalar( get_post_meta( $id, '_wfo_code', true ) ),
			'status'      => $status,
			'created'     => wp_date( 'c', (int) get_post_timestamp( $post ) ),
			'type'        => $order_type,
			'payment'     => $payment,
			'customer'    => $customer,
			'items'       => $items_out,
			'totals'      => $totals,
			'message'     => self::scalar( get_post_meta( $id, '_wfo_message', true ) ),
			'lang'        => self::scalar( get_post_meta( $id, '_wfo_lang', true ) ),
			'total_items' => $total_items,
		);
	}

	/**
	 * Move an order between the allowed statuses.
	 *
	 * @param int    $id     Order post id.
	 * @param string $status "new" or "handled".
	 * @return bool True when the order now has that status.
	 */
	public static function mark( $id, $status ) {
		$id = absint( $id );

		if ( ! is_scalar( $status ) ) {
			return false;
		}

		$status = sanitize_key( (string) $status );

		if ( ! $id || ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}

		$post = get_post( $id );

		if ( ! $post || WFO_CPT_ORDER !== $post->post_type ) {
			return false;
		}

		update_post_meta( $id, '_wfo_status', $status );

		return self::scalar( get_post_meta( $id, '_wfo_status', true ) ) === $status;
	}

	/**
	 * Generate a unique order code: order prefix + five base32 characters.
	 *
	 * The alphabet omits 0/O and 1/I so codes are easy to read over the phone.
	 *
	 * @return string
	 */
	public static function generate_code() {
		$prefix = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) Settings::get( 'order_prefix', 'WFO' ) ) );
		$prefix = '' !== $prefix ? substr( $prefix, 0, 8 ) : 'WFO';

		$alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
		$code     = '';

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$suffix = '';

			for ( $i = 0; $i < 5; $i++ ) {
				$suffix .= $alphabet[ wp_rand( 0, strlen( $alphabet ) - 1 ) ];
			}

			$code     = $prefix . '-' . $suffix;
			$existing = get_posts(
				array(
					'post_type'        => WFO_CPT_ORDER,
					'post_status'      => 'any',
					'fields'           => 'ids',
					'posts_per_page'   => 1,
					'no_found_rows'    => true,
					'suppress_filters' => false,
					'meta_query'       => array(
						array(
							'key'   => '_wfo_code',
							'value' => $code,
						),
					),
				)
			);

			if ( empty( $existing ) ) {
				break;
			}
		}

		return $code;
	}

	/**
	 * WhatsApp link for an order.
	 *
	 * @param string $message Order message.
	 * @param string $number  Optional number; the configured one is used when empty.
	 * @return string
	 */
	public static function wa_url( $message, $number = '' ) {
		return WhatsApp::link( (string) $message, (string) $number );
	}

	/**
	 * Permanently delete orders older than a number of days.
	 *
	 * @param int $days Age in days; 0 or less keeps everything.
	 * @return int Number of deleted orders.
	 */
	public static function prune( $days ) {
		$days = (int) $days;

		if ( $days <= 0 ) {
			return 0;
		}

		$before  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$deleted = 0;

		do {
			$ids = get_posts(
				array(
					'post_type'        => WFO_CPT_ORDER,
					'post_status'      => 'any',
					'fields'           => 'ids',
					'posts_per_page'   => 100,
					'no_found_rows'    => true,
					'suppress_filters' => false,
					'date_query'       => array(
						array(
							'column'    => 'post_date_gmt',
							'before'    => $before,
							'inclusive' => true,
						),
					),
				)
			);

			$progress = false;

			foreach ( $ids as $id ) {
				if ( wp_delete_post( (int) $id, true ) ) {
					++$deleted;
					$progress = true;
				}
			}

			// Stop when the batch was short or nothing could be deleted.
		} while ( count( $ids ) >= 100 && $progress );

		return $deleted;
	}

	/**
	 * Persist the order meta.
	 *
	 * @param int   $order_id Order post id.
	 * @param array $data     Normalized order data.
	 */
	private static function save_meta( $order_id, $data ) {
		update_post_meta( $order_id, '_wfo_code', $data['code'] );
		update_post_meta( $order_id, '_wfo_items', $data['items'] );
		update_post_meta( $order_id, '_wfo_customer', $data['customer'] );
		update_post_meta( $order_id, '_wfo_totals', $data['totals'] );
		update_post_meta( $order_id, '_wfo_message', $data['message'] );
		update_post_meta( $order_id, '_wfo_lang', $data['lang'] );
		update_post_meta( $order_id, '_wfo_payment', $data['payment'] );
		update_post_meta( $order_id, '_wfo_type', $data['type'] );
		update_post_meta( $order_id, '_wfo_status', 'new' );
		update_post_meta( $order_id, '_wfo_ip_hash', $data['ip_hash'] );

		if ( null !== $data['price_mismatch'] ) {
			update_post_meta( $order_id, '_wfo_price_mismatch', (int) $data['price_mismatch'] );
		}
	}

	/**
	 * Send the notification email to the restaurant.
	 *
	 * @param array $data     Normalized order data.
	 * @param array $settings Plugin settings.
	 * @return bool Whether wp_mail accepted the message.
	 */
	private static function notify( $data, $settings ) {
		$to = isset( $settings['notify_email'] ) ? (string) $settings['notify_email'] : '';

		if ( ! is_email( $to ) ) {
			$to = (string) get_option( 'admin_email' );
		}

		if ( ! is_email( $to ) ) {
			return false;
		}

		$restaurant = ! empty( $settings['restaurant_name'] ) ? (string) $settings['restaurant_name'] : (string) get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: 1: restaurant or site name, 2: order code. */
			__( '[%1$s] New order %2$s', 'whatsapp-menu-orders' ),
			$restaurant,
			$data['code']
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		// The stored phone is digits only, so this is practically always false.
		if ( is_email( $data['customer']['phone'] ) ) {
			$headers[] = 'Reply-To: ' . $data['customer']['phone'];
		}

		/*
		 * Send the notification from the restaurant rather than the default
		 * "WordPress <wordpress@site>" sender, and attach a plain-text part so
		 * the message scores better with spam filters. Both are scoped to this
		 * one call and removed again immediately.
		 */
		$from_name  = $restaurant;
		$from_email = (string) apply_filters( 'wfo_email_from_address', (string) get_option( 'admin_email' ) );
		$plain      = self::email_plain( $data, $settings );

		$filter_name = static function () use ( $from_name ) {
			return $from_name;
		};
		$filter_mail = static function () use ( $from_email ) {
			return $from_email;
		};
		$set_alt     = static function ( $phpmailer ) use ( $plain ) {
			$phpmailer->AltBody = $plain;
		};

		add_filter( 'wp_mail_from_name', $filter_name );
		add_filter( 'wp_mail_from', $filter_mail );
		add_action( 'phpmailer_init', $set_alt );

		$sent = wp_mail( $to, $subject, self::email_body( $data, $settings ), $headers );

		remove_filter( 'wp_mail_from_name', $filter_name );
		remove_filter( 'wp_mail_from', $filter_mail );
		remove_action( 'phpmailer_init', $set_alt );

		return (bool) $sent;
	}

	/**
	 * Plain-text alternative for the notification email.
	 *
	 * @param array $data     Normalized order data.
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	private static function email_plain( $data, $settings ) {
		$customer = $data['customer'];
		$totals   = $data['totals'];

		$lines = array(
			sprintf(
				/* translators: %s: order code. */
				__( 'New order %s', 'whatsapp-menu-orders' ),
				$data['code']
			),
			'',
			sprintf(
				/* translators: 1: customer name, 2: phone. */
				__( 'Customer: %1$s · %2$s', 'whatsapp-menu-orders' ),
				$customer['name'],
				$customer['phone']
			),
			sprintf(
				/* translators: %s: delivery or pickup. */
				__( 'Type: %s', 'whatsapp-menu-orders' ),
				'delivery' === $data['type'] ? __( 'Delivery', 'whatsapp-menu-orders' ) : __( 'Pickup', 'whatsapp-menu-orders' )
			),
		);

		if ( 'delivery' === $data['type'] && '' !== $customer['address'] ) {
			$lines[] = sprintf(
				/* translators: %s: delivery address. */
				__( 'Address: %s', 'whatsapp-menu-orders' ),
				$customer['address']
			);
		}

		$lines[] = sprintf(
			/* translators: %s: cash or bank transfer. */
			__( 'Payment: %s', 'whatsapp-menu-orders' ),
			'bank' === $data['payment'] ? __( 'Bank transfer', 'whatsapp-menu-orders' ) : __( 'Cash', 'whatsapp-menu-orders' )
		);
		$lines[] = '';
		$lines[] = __( 'Items', 'whatsapp-menu-orders' );

		foreach ( $data['items'] as $item ) {
			$lines[] = sprintf(
				/* translators: 1: quantity, 2: item name, 3: line total. */
				__( '%1$d × %2$s — %3$s', 'whatsapp-menu-orders' ),
				(int) $item['qty'],
				$item['name'],
				Money::format( (int) $item['total'], $settings )
			);

			foreach ( $item['options'] as $option ) {
				$lines[] = '   + ' . $option['label'];
			}

			if ( '' !== $item['note'] ) {
				$lines[] = '   · ' . $item['note'];
			}
		}

		$lines[] = '';
		$lines[] = __( 'Subtotal', 'whatsapp-menu-orders' ) . ': ' . Money::format( (int) $totals['subtotal'], $settings );
		$lines[] = __( 'Delivery', 'whatsapp-menu-orders' ) . ': ' . Money::format( (int) $totals['delivery'], $settings );
		$lines[] = __( 'Total', 'whatsapp-menu-orders' ) . ': ' . Money::format( (int) $totals['total'], $settings );

		if ( '' !== $customer['note'] ) {
			$lines[] = '';
			$lines[] = __( 'Note', 'whatsapp-menu-orders' ) . ': ' . $customer['note'];
		}

		$lines[] = '';
		$lines[] = __( 'WhatsApp message', 'whatsapp-menu-orders' );
		$lines[] = $data['message'];

		return implode( "\n", $lines );
	}

	/**
	 * HTML notification email: a summary table, the items and the exact message.
	 *
	 * @param array $data     Normalized order data.
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	private static function email_body( $data, $settings ) {
		$customer = $data['customer'];
		$totals   = $data['totals'];

		$type_label = 'delivery' === $data['type']
			? __( 'Delivery', 'whatsapp-menu-orders' )
			: __( 'Pickup', 'whatsapp-menu-orders' );

		$payment_label = 'bank' === $data['payment']
			? __( 'Bank transfer', 'whatsapp-menu-orders' )
			: __( 'Cash', 'whatsapp-menu-orders' );

		$time_label = ( 'asap' === $customer['time'] )
			? __( 'As soon as possible', 'whatsapp-menu-orders' )
			: $customer['time'];

		$fields = array(
			__( 'Order code', 'whatsapp-menu-orders' )  => $data['code'],
			__( 'Name', 'whatsapp-menu-orders' )        => $customer['name'],
			__( 'Phone', 'whatsapp-menu-orders' )       => $customer['phone'],
			__( 'Order type', 'whatsapp-menu-orders' )  => $type_label,
			__( 'Payment', 'whatsapp-menu-orders' )     => $payment_label,
			__( 'When', 'whatsapp-menu-orders' )        => $time_label,
		);

		if ( 'delivery' === $data['type'] ) {
			$fields[ __( 'Address', 'whatsapp-menu-orders' ) ] = $customer['address'];
		}

		if ( '' !== $customer['note'] ) {
			$fields[ __( 'Note', 'whatsapp-menu-orders' ) ] = $customer['note'];
		}

		$html  = '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;color:#14110f;line-height:1.5">';
		$html .= '<h2 style="margin:0 0 16px;font-size:20px">' . esc_html(
			sprintf(
				/* translators: %s: order code. */
				__( 'New order %s', 'whatsapp-menu-orders' ),
				$data['code']
			)
		) . '</h2>';

		$html .= '<table cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse;width:100%;max-width:640px">';

		foreach ( $fields as $label => $value ) {
			$html .= '<tr>'
				. '<th align="left" style="border-bottom:1px solid #e8e2dc;font-weight:600;width:140px">' . esc_html( $label ) . '</th>'
				. '<td style="border-bottom:1px solid #e8e2dc">' . esc_html( (string) $value ) . '</td>'
				. '</tr>';
		}

		$html .= '</table>';

		$html .= '<h3 style="margin:24px 0 8px;font-size:16px">' . esc_html__( 'Items', 'whatsapp-menu-orders' ) . '</h3>';
		$html .= '<table cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse;width:100%;max-width:640px">';

		foreach ( $data['items'] as $item ) {
			$html .= '<tr>';
			$html .= '<td style="border-bottom:1px solid #e8e2dc;vertical-align:top">' . esc_html(
				sprintf(
					/* translators: 1: quantity, 2: item name. */
					__( '%1$d × %2$s', 'whatsapp-menu-orders' ),
					(int) $item['qty'],
					$item['name']
				)
			);

			foreach ( $item['options'] as $option ) {
				if ( 0 === (int) $option['price'] ) {
					$option_line = '+ ' . $option['label'];
				} else {
					$option_line = sprintf(
						'%1$s %2$s (%3$s)',
						$option['price'] < 0 ? '−' : '+',
						$option['label'],
						Money::format( abs( (int) $option['price'] ), $settings )
					);
				}

				$html .= '<br><span style="color:#6f6862">' . esc_html( $option_line ) . '</span>';
			}

			if ( '' !== $item['note'] ) {
				$html .= '<br><span style="color:#6f6862">' . esc_html( '· ' . $item['note'] ) . '</span>';
			}

			$html .= '</td>';
			$html .= '<td align="right" style="border-bottom:1px solid #e8e2dc;vertical-align:top;white-space:nowrap">' . esc_html( Money::format( (int) $item['total'], $settings ) ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '<tr><td align="right">' . esc_html__( 'Subtotal', 'whatsapp-menu-orders' ) . '</td><td align="right">' . esc_html( Money::format( (int) $totals['subtotal'], $settings ) ) . '</td></tr>';
		$html .= '<tr><td align="right">' . esc_html__( 'Delivery', 'whatsapp-menu-orders' ) . '</td><td align="right">' . esc_html( Money::format( (int) $totals['delivery'], $settings ) ) . '</td></tr>';
		$html .= '<tr><td align="right"><strong>' . esc_html__( 'Total', 'whatsapp-menu-orders' ) . '</strong></td><td align="right"><strong>' . esc_html( Money::format( (int) $totals['total'], $settings ) ) . '</strong></td></tr>';
		$html .= '</table>';

		$html .= '<h3 style="margin:24px 0 8px;font-size:16px">' . esc_html__( 'WhatsApp message', 'whatsapp-menu-orders' ) . '</h3>';
		$html .= '<pre style="background:#faf7f4;border:1px solid #e8e2dc;border-radius:8px;padding:12px;white-space:pre-wrap;word-break:break-word;font-size:13px">' . esc_html( $data['message'] ) . '</pre>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Read a client-side total, if the browser sent one.
	 *
	 * @param array $payload Request payload.
	 * @return int|null Total in minor units.
	 */
	private static function client_total( $payload ) {
		$candidates = array(
			self::value( $payload, 'total', null ),
			self::value( $payload, 'client_total', null ),
		);

		$totals = self::value( $payload, 'totals', null );

		if ( is_array( $totals ) && isset( $totals['total'] ) ) {
			$candidates[] = $totals['total'];
		}

		foreach ( $candidates as $candidate ) {
			if ( is_float( $candidate ) || is_int( $candidate ) || ( is_string( $candidate ) && is_numeric( $candidate ) ) ) {
				return (int) round( (float) $candidate );
			}
		}

		return null;
	}

	/**
	 * Hashed client IP for abuse triage, never the raw address.
	 *
	 * @return string
	 */
	private static function ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filter the client IP used for the order audit hash.
		 *
		 * @param string $ip Detected IP.
		 */
		$ip = (string) apply_filters( 'wfo_client_ip', $ip );

		if ( '' === $ip ) {
			return '';
		}

		return hash( 'sha256', $ip . '|' . wp_salt( 'auth' ) );
	}

	/**
	 * Return a code that no stored order uses yet.
	 *
	 * @param string $code Requested code.
	 * @return string
	 */
	private static function unique_code( $code ) {
		if ( ! self::code_in_use( $code ) ) {
			return $code;
		}

		$base = $code;

		for ( $i = 2; $i <= 20; $i++ ) {
			$suffix    = '-' . $i;
			$candidate = ( strlen( $base ) + strlen( $suffix ) > 20 )
				? substr( $base, 0, 20 - strlen( $suffix ) ) . $suffix
				: $base . $suffix;

			if ( ! self::code_in_use( $candidate ) ) {
				return $candidate;
			}
		}

		return substr( $base, 0, 15 ) . '-' . strtoupper( substr( md5( uniqid( '', true ) ), 0, 4 ) );
	}

	/**
	 * Is this order code already stored?
	 *
	 * @param string $code Order code.
	 * @return bool
	 */
	private static function code_in_use( $code ) {
		$found = get_posts(
			array(
				'post_type'              => WFO_CPT_ORDER,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_wfo_code',
				'meta_value'             => $code,
			)
		);

		return ! empty( $found );
	}

	/**
	 * Build a WP_Error carrying an HTTP status for the REST layer.
	 *
	 * @param string $code    Machine readable code.
	 * @param string $message Translated message.
	 * @param int    $status  HTTP status.
	 * @param array  $extra   Extra error data.
	 * @return \WP_Error
	 */
	private static function error( $code, $message, $status = 400, $extra = array() ) {
		$data = array_merge( array( 'status' => (int) $status ), (array) $extra );

		return new \WP_Error( $code, $message, $data );
	}

	/**
	 * Read a payload key with a default.
	 *
	 * @param array  $array   Source array.
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	private static function value( $array, $key, $default = '' ) {
		return ( is_array( $array ) && array_key_exists( $key, $array ) ) ? $array[ $key ] : $default;
	}

	/**
	 * Sanitize a single-line string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function text( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return trim( sanitize_text_field( (string) $value ) );
	}

	/**
	 * Sanitize a multi-line string, preserving newlines.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function textarea( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return trim( sanitize_textarea_field( (string) $value ) );
	}

	/**
	 * Cast stored meta to a string without ever raising on arrays/objects.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function scalar( $value ) {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Character length, multibyte aware when possible.
	 *
	 * @param string $value String.
	 * @return int
	 */
	private static function length( $value ) {
		return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}
}
