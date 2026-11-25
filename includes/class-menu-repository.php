<?php
/**
 * Reads menu items and shapes the payload used by PHP templates and the JS app.
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * Menu data access.
 */
class Menu_Repository {

	/**
	 * Cached payload per args hash.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Published menu items.
	 *
	 * @param array $args Query args: categories, limit, include_unavailable.
	 * @return array
	 */
	public static function items( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'categories'          => array(),
				'limit'               => -1,
				'include_unavailable' => false,
			)
		);

		$query_args = array(
			'post_type'              => WFO_CPT_ITEM,
			'post_status'            => 'publish',
			'posts_per_page'         => (int) $args['limit'] > 0 ? (int) $args['limit'] : -1,
			'orderby'                => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'          => true,
			'update_post_term_cache' => true,
			'update_post_meta_cache' => true,
		);

		if ( ! empty( $args['categories'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => WFO_TAX_CAT,
					'field'    => 'term_id',
					'terms'    => array_map( 'absint', (array) $args['categories'] ),
				),
			);
		}

		if ( empty( $args['include_unavailable'] ) ) {
			$query_args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => '_wfo_available',
					'value'   => '1',
					'compare' => '=',
				),
				array(
					'key'     => '_wfo_available',
					'compare' => 'NOT EXISTS',
				),
			);
		}

		/**
		 * Filter the menu query args.
		 *
		 * @param array $query_args WP_Query args.
		 * @param array $args       Repository args.
		 */
		$query_args = apply_filters( 'wfo_menu_query_args', $query_args, $args );
		$query      = new \WP_Query( $query_args );
		$items      = array();

		foreach ( $query->posts as $post ) {
			$item = self::shape( $post );

			if ( $item ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * A single item.
	 *
	 * @param int $id Post id.
	 * @return array|null
	 */
	public static function item( $id ) {
		$post = get_post( (int) $id );

		if ( ! $post || WFO_CPT_ITEM !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		return self::shape( $post );
	}

	/**
	 * Shape a post into the array consumed by templates and JS.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array|null
	 */
	public static function shape( $post ) {
		$id       = (int) $post->ID;
		$regular  = (int) get_post_meta( $id, '_wfo_price', true );
		$sale     = (int) get_post_meta( $id, '_wfo_sale_price', true );
		$on_sale  = $sale > 0 && $sale < $regular;
		$name     = get_the_title( $post );
		$desc     = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
		$desc     = wp_strip_all_tags( (string) $desc );
		$name_es  = (string) get_post_meta( $id, '_wfo_name_es', true );
		$desc_es  = (string) get_post_meta( $id, '_wfo_desc_es', true );

		if ( I18n::is_spanish() ) {
			if ( '' !== trim( $name_es ) ) {
				$name = $name_es;
			}
			if ( '' !== trim( $desc_es ) ) {
				$desc = $desc_es;
			}
		}

		$image = null;
		$thumb = (int) get_post_thumbnail_id( $id );

		if ( $thumb ) {
			$src = wp_get_attachment_image_src( $thumb, 'large' );
			if ( $src ) {
				$image = array(
					'url'    => $src[0],
					'width'  => (int) $src[1],
					'height' => (int) $src[2],
					'srcset' => (string) wp_get_attachment_image_srcset( $thumb, 'large' ),
					'alt'    => (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ),
				);
			}
		}

		$available = get_post_meta( $id, '_wfo_available', true );
		$terms     = wp_get_post_terms( $id, WFO_TAX_CAT, array( 'fields' => 'ids' ) );
		$terms     = is_wp_error( $terms ) ? array() : array_map( 'intval', (array) $terms );

		return array(
			'id'            => $id,
			'name'          => $name,
			'description'   => $desc,
			'price'         => $on_sale ? $sale : $regular,
			'compare_price' => $on_sale ? $regular : 0,
			'image'         => $image,
			'available'     => ( '' === $available || '1' === (string) $available ),
			'badges'        => self::badges( $id ),
			'categories'    => $terms,
			'order'         => (int) $post->menu_order,
			'groups'        => self::groups( $id ),
		);
	}

	/**
	 * Normalized option groups for an item.
	 *
	 * @param int $id Post id.
	 * @return array
	 */
	public static function groups( $id ) {
		$raw    = get_post_meta( $id, '_wfo_groups', true );
		$groups = array();

		if ( ! is_array( $raw ) ) {
			return $groups;
		}

		foreach ( $raw as $index => $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$label = isset( $group['label'] ) ? sanitize_text_field( $group['label'] ) : '';
			$type  = ( isset( $group['type'] ) && 'radio' === $group['type'] ) ? 'radio' : 'checkbox';
			$gid   = ! empty( $group['id'] ) ? sanitize_key( $group['id'] ) : 'g' . ( $index + 1 );
			$opts  = array();

			$raw_options = isset( $group['options'] ) && is_array( $group['options'] ) ? $group['options'] : array();

			foreach ( $raw_options as $option_index => $option ) {
				if ( ! is_array( $option ) ) {
					continue;
				}

				$option_label = isset( $option['label'] ) ? sanitize_text_field( $option['label'] ) : '';

				if ( '' === $option_label ) {
					continue;
				}

				$opts[] = array(
					'id'      => ! empty( $option['id'] ) ? sanitize_key( $option['id'] ) : 'o' . ( $option_index + 1 ),
					'label'   => $option_label,
					'price'   => isset( $option['price'] ) ? (int) $option['price'] : 0,
					'default' => ! empty( $option['default'] ),
					'soldout' => ! empty( $option['soldout'] ),
				);
			}

			if ( ! $opts || '' === $label ) {
				continue;
			}

			$required = ! empty( $group['required'] );
			$min      = isset( $group['min'] ) ? max( 0, (int) $group['min'] ) : 0;
			$max      = isset( $group['max'] ) ? max( 0, (int) $group['max'] ) : 0;

			if ( 'radio' === $type ) {
				$min = $required ? 1 : 0;
				$max = 1;
			}

			$groups[] = array(
				'id'       => $gid,
				'label'    => $label,
				'type'     => $type,
				'required' => $required,
				'min'      => $min,
				'max'      => $max,
				'options'  => $opts,
			);
		}

		return $groups;
	}

	/**
	 * Item badges.
	 *
	 * @param int $id Post id.
	 * @return array
	 */
	private static function badges( $id ) {
		$raw = get_post_meta( $id, '_wfo_badges', true );

		if ( is_string( $raw ) ) {
			$raw = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$badges = array();

		foreach ( $raw as $badge ) {
			if ( is_array( $badge ) ) {
				// Accept both the canonical shape and a plain [ label, tone ] pair.
				$label = '';
				$tone  = 'default';

				if ( isset( $badge['label'] ) ) {
					$label = sanitize_text_field( (string) $badge['label'] );
					$tone  = isset( $badge['tone'] ) ? sanitize_key( (string) $badge['tone'] ) : 'default';
				} elseif ( isset( $badge[0] ) ) {
					$label = sanitize_text_field( (string) $badge[0] );
					$tone  = isset( $badge[1] ) ? sanitize_key( (string) $badge[1] ) : 'default';
				}
			} else {
				$label = sanitize_text_field( (string) $badge );
				$tone  = 'default';
			}

			if ( '' === $label ) {
				continue;
			}

			$badges[] = array(
				'label' => $label,
				'tone'  => in_array( $tone, array( 'hot', 'new', 'veg', 'default' ), true ) ? $tone : 'default',
			);
		}

		return $badges;
	}

	/**
	 * Categories that actually hold items.
	 *
	 * @param bool $hide_empty Hide empty terms.
	 * @return array
	 */
	public static function categories( $hide_empty = true ) {
		$terms = get_terms(
			array(
				'taxonomy'   => WFO_TAX_CAT,
				'hide_empty' => (bool) $hide_empty,
				// Creation order is the category order a restaurant expects;
				// alphabetical order would scramble a hand-built menu.
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$out = array();

		foreach ( $terms as $term ) {
			$name = $term->name;
			$desc = $term->description;

			if ( I18n::is_spanish() ) {
				$name_es = (string) get_term_meta( $term->term_id, 'wfo_name_es', true );
				$desc_es = (string) get_term_meta( $term->term_id, 'wfo_desc_es', true );

				if ( '' !== trim( $name_es ) ) {
					$name = $name_es;
				}

				if ( '' !== trim( $desc_es ) ) {
					$desc = $desc_es;
				}
			}

			$out[] = array(
				'id'          => (int) $term->term_id,
				'name'        => $name,
				'slug'        => $term->slug,
				'description' => $desc,
				'count'       => (int) $term->count,
			);
		}

		return $out;
	}

	/**
	 * Everything the front end needs.
	 *
	 * @param array $args Render args.
	 * @return array
	 */
	public static function payload( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'categories' => array(),
				'limit'      => -1,
				'instance'   => '',
			)
		);

		$hash = md5( wp_json_encode( $args ) . I18n::locale() );

		if ( isset( self::$cache[ $hash ] ) ) {
			return self::$cache[ $hash ];
		}

		$settings = Settings::all();
		$items    = self::items( $args );
		$cats     = self::categories();

		if ( ! empty( $args['categories'] ) ) {
			$allowed = array_map( 'absint', (array) $args['categories'] );
			$cats    = array_values(
				array_filter(
					$cats,
					static function ( $cat ) use ( $allowed ) {
						return in_array( (int) $cat['id'], $allowed, true );
					}
				)
			);
		}

		$hours = Hours::state();
		$open  = $hours['open'];

		$payload = array(
			'instance'  => $args['instance'],
			'endpoint'  => esc_url_raw( rest_url( 'wfo/v1/orders' ) ),
			'currency'  => array(
				'code'      => $settings['currency'],
				'symbol'    => Money::symbol( $settings['currency'] ),
				'decimals'  => (int) $settings['decimals'],
				'position'  => $settings['currency_position'],
				'thousands' => $settings['thousands_sep'],
				'decimal'   => $settings['decimal_sep'],
			),
			'settings'  => array(
				'enablePickup'     => (bool) $settings['enable_pickup'],
				'enableDelivery'   => (bool) $settings['enable_delivery'],
				'deliveryFee'      => (int) $settings['delivery_fee'],
				'freeDeliveryOver' => (int) $settings['free_delivery_over'],
				'minOrder'         => (int) $settings['min_order'],
				'minOrderScope'    => $settings['min_order_scope'],
				'deliveryNote'     => $settings['delivery_note'],
				'deliveryFeeLabel' => $settings['delivery_fee_label'],
				'defaultType'      => $settings['default_order_type'],
				'mapsKey'          => $settings['google_maps_key'],
				'mapCenter'        => $settings['map_center'],
				'paymentCash'      => (bool) $settings['payment_cash'],
				'paymentBank'      => (bool) $settings['payment_bank'],
				'bankDetails'      => $settings['bank_details'],
				'bankNote'         => $settings['bank_note'],
				'paymentNote'      => $settings['payment_note'],
				'closedMessage'    => $settings['closed_message'],
				'orderPrefix'      => $settings['order_prefix'],
				'messageTemplate'  => $settings['message_template'],
				'showSearch'       => (bool) $settings['show_search'] && ! empty( $args['show_search'] ),
				'restaurantName'   => $settings['restaurant_name'] ? $settings['restaurant_name'] : get_bloginfo( 'name' ),
				'whatsapp'         => '' !== $settings['whatsapp_number'],
				'whatsappNumber'   => WhatsApp::normalize( $settings['whatsapp_number'], $settings['country_code'] ),
			),
			'open'      => $open,
			'hoursLabel' => $hours['label'],
			'nextOpening' => $hours['next'],
			'items'     => $items,
			'categories' => $cats,
			'strings'   => self::strings(),
		);

		self::$cache[ $hash ] = $payload;

		return $payload;
	}

	/**
	 * Front-end strings handed to the JS app.
	 *
	 * @return array
	 */
	public static function strings() {
		return array(
			'add'                => __( 'Add', 'whatsapp-menu-orders' ),
			'addToOrder'         => __( 'Add to order', 'whatsapp-menu-orders' ),
			'updateOrder'        => __( 'Update order', 'whatsapp-menu-orders' ),
			'soldOut'            => __( 'Sold out', 'whatsapp-menu-orders' ),
			'unavailable'        => __( 'Currently unavailable', 'whatsapp-menu-orders' ),
			'choose'             => __( 'Choose', 'whatsapp-menu-orders' ),
			'required'           => __( 'Required', 'whatsapp-menu-orders' ),
			'optional'           => __( 'Optional', 'whatsapp-menu-orders' ),
			/* translators: %d: maximum number of options a customer may pick in a group. */
			'upTo'               => __( 'up to %d', 'whatsapp-menu-orders' ),
			'specialRequests'    => __( 'Special requests', 'whatsapp-menu-orders' ),
			'specialRequestsHint' => __( 'No onions, extra spicy, allergies…', 'whatsapp-menu-orders' ),
			'quantity'           => __( 'Quantity', 'whatsapp-menu-orders' ),
			'subtotal'           => __( 'Subtotal', 'whatsapp-menu-orders' ),
			'delivery'           => __( 'Delivery', 'whatsapp-menu-orders' ),
			'free'               => __( 'Free', 'whatsapp-menu-orders' ),
			'total'              => __( 'Total', 'whatsapp-menu-orders' ),
			'yourOrder'          => __( 'Your order', 'whatsapp-menu-orders' ),
			'emptyCart'          => __( 'Your order is empty.', 'whatsapp-menu-orders' ),
			'emptyCartHint'      => __( 'Pick something from the menu to get started.', 'whatsapp-menu-orders' ),
			'continue'           => __( 'Keep browsing', 'whatsapp-menu-orders' ),
			'yourDetails'        => __( 'Your details', 'whatsapp-menu-orders' ),
			'name'               => __( 'Name', 'whatsapp-menu-orders' ),
			'phone'              => __( 'Phone', 'whatsapp-menu-orders' ),
			'orderType'          => __( 'Order type', 'whatsapp-menu-orders' ),
			'pickup'             => __( 'Pickup', 'whatsapp-menu-orders' ),
			'delivery_'          => __( 'Delivery', 'whatsapp-menu-orders' ),
			'address'            => __( 'Address', 'whatsapp-menu-orders' ),
			'addressPlaceholder' => __( 'Street, number, floor, references', 'whatsapp-menu-orders' ),
			'location'           => __( 'Location', 'whatsapp-menu-orders' ),
			'mapHint'            => __( 'Optional: tap the map or drag the pin to your door.', 'whatsapp-menu-orders' ),
			'mapLocate'          => __( 'Use my location', 'whatsapp-menu-orders' ),
			'mapLocating'        => __( 'Finding you…', 'whatsapp-menu-orders' ),
			'mapLocateError'     => __( 'We could not get your location. Place the pin by hand.', 'whatsapp-menu-orders' ),
			'mapPinSet'          => __( 'Pin saved. We will send it with your order.', 'whatsapp-menu-orders' ),
			'mapClear'           => __( 'Remove pin', 'whatsapp-menu-orders' ),
			'mapLabel'           => __( 'Map to mark your delivery location', 'whatsapp-menu-orders' ),
			'fillAddressOrPin'   => __( 'Write your address or place the pin on the map.', 'whatsapp-menu-orders' ),
			'when'               => __( 'When', 'whatsapp-menu-orders' ),
			'timeLabel'          => __( 'Time', 'whatsapp-menu-orders' ),
			'asap'               => __( 'As soon as possible', 'whatsapp-menu-orders' ),
			'scheduled'          => __( 'Schedule', 'whatsapp-menu-orders' ),
			'note'               => __( 'Note for the restaurant', 'whatsapp-menu-orders' ),
			'notePlaceholder'    => __( 'Anything else we should know?', 'whatsapp-menu-orders' ),
			'payment'            => __( 'Payment', 'whatsapp-menu-orders' ),
			'cash'               => __( 'Cash', 'whatsapp-menu-orders' ),
			'bank'               => __( 'Bank transfer', 'whatsapp-menu-orders' ),
			'cashHint'           => __( 'Pay on delivery or pickup.', 'whatsapp-menu-orders' ),
			'bankHint'           => __( 'We will send the account details on WhatsApp.', 'whatsapp-menu-orders' ),
			'bankTransferTo'     => __( 'Send the transfer to this account and share the receipt on WhatsApp.', 'whatsapp-menu-orders' ),
			'minOrder'           => __( 'Minimum order', 'whatsapp-menu-orders' ),
			/* translators: %s: remaining amount, formatted with currency. */
			'minOrderNotice'     => __( 'Add %s more to reach the minimum order.', 'whatsapp-menu-orders' ),
			'sendOnWhatsApp'     => __( 'Send order on WhatsApp', 'whatsapp-menu-orders' ),
			'sending'            => __( 'Sending…', 'whatsapp-menu-orders' ),
			'sendingHint'        => __( 'WhatsApp opens with your order ready to send.', 'whatsapp-menu-orders' ),
			'orderSent'          => __( 'Order ready', 'whatsapp-menu-orders' ),
			'orderSentHint'      => __( 'Send the message in WhatsApp to confirm. We reply with the total and payment details.', 'whatsapp-menu-orders' ),
			'orderCode'          => __( 'Order code', 'whatsapp-menu-orders' ),
			'paymentInstructions' => __( 'Payment instructions', 'whatsapp-menu-orders' ),
			'copyOrder'          => __( 'Copy order', 'whatsapp-menu-orders' ),
			'copied'             => __( 'Copied', 'whatsapp-menu-orders' ),
			'close'              => __( 'Close', 'whatsapp-menu-orders' ),
			'remove'             => __( 'Remove', 'whatsapp-menu-orders' ),
			'edit'               => __( 'Edit', 'whatsapp-menu-orders' ),
			'item'               => __( 'item', 'whatsapp-menu-orders' ),
			'items'              => __( 'items', 'whatsapp-menu-orders' ),
			'businessClosed'     => __( 'We are closed right now', 'whatsapp-menu-orders' ),
			'businessClosedHint' => __( 'You can browse the menu and send your order when we open.', 'whatsapp-menu-orders' ),
			'search'             => __( 'Search', 'whatsapp-menu-orders' ),
			'searchPlaceholder'  => __( 'Search the menu', 'whatsapp-menu-orders' ),
			'noResults'          => __( 'Nothing matches that search.', 'whatsapp-menu-orders' ),
			'requiredField'      => __( 'Please fill this in.', 'whatsapp-menu-orders' ),
			'invalidPhone'       => __( 'Enter a valid phone number.', 'whatsapp-menu-orders' ),
			'choosePayment'      => __( 'Choose how you would like to pay.', 'whatsapp-menu-orders' ),
			'chooseType'         => __( 'Choose pickup or delivery.', 'whatsapp-menu-orders' ),
			'fillAddress'        => __( 'We need an address for delivery.', 'whatsapp-menu-orders' ),
			'errorGeneric'       => __( 'Something went wrong. Please try again.', 'whatsapp-menu-orders' ),
			'errorNetwork'       => __( 'We could not reach the server, but your order is ready in WhatsApp.', 'whatsapp-menu-orders' ),
			'qty'                => __( 'Qty', 'whatsapp-menu-orders' ),
			'backToMenu'         => __( 'Back to menu', 'whatsapp-menu-orders' ),
			'viewOrder'          => __( 'View order', 'whatsapp-menu-orders' ),
			'whatsappHint'       => __( 'No account, no checkout. Your order is sent as a WhatsApp message.', 'whatsapp-menu-orders' ),
			/* translators: %s: order subtotal, formatted with currency. */
			'freeDeliveryOver'   => __( 'Free delivery over %s', 'whatsapp-menu-orders' ),
			/* translators: %s: remaining amount, formatted with currency. */
			'remaining'          => __( '%s to go', 'whatsapp-menu-orders' ),
			'openNow'            => __( 'Open now', 'whatsapp-menu-orders' ),
			'closedNow'          => __( 'Closed', 'whatsapp-menu-orders' ),
			/* translators: %s: menu item name. */
			'optionsFor'         => __( 'Options for %s', 'whatsapp-menu-orders' ),
			'orderReady'         => __( 'Your order', 'whatsapp-menu-orders' ),
			'deliveryFeeNote'    => __( 'Delivery fee', 'whatsapp-menu-orders' ),
			'freeDelivery'       => __( 'Free delivery', 'whatsapp-menu-orders' ),
			'notAvailable'       => __( 'Ordering is not available right now.', 'whatsapp-menu-orders' ),
			'whatsappMissing'    => __( 'WhatsApp number is not configured yet.', 'whatsapp-menu-orders' ),
			'callToOrder'        => __( 'Call to order', 'whatsapp-menu-orders' ),
			'addMore'            => __( 'Add more items', 'whatsapp-menu-orders' ),
			'yourOrderTotal'     => __( 'Your total', 'whatsapp-menu-orders' ),
			'hoursToday'         => __( 'Today', 'whatsapp-menu-orders' ),
		);
	}
}
