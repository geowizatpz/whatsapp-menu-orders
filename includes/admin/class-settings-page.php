<?php
/**
 * Tabbed settings screen for the whole plugin.
 *
 * @package WFO
 */

namespace WFO\Admin;

use WFO\Money;
use WFO\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves the wfo_settings option and the extra presets.
 */
class Settings_Page {

	/**
	 * Forced tab when the Extras submenu page is rendered.
	 *
	 * @var string
	 */
	private static $forced_tab = '';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_post_wfo_save_settings', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Tabs of the settings screen.
	 *
	 * @return array<string,string>
	 */
	private static function tabs() {
		return array(
			'general'  => __( 'General', 'whatsapp-menu-orders' ),
			'ordering' => __( 'Ordering', 'whatsapp-menu-orders' ),
			'payments' => __( 'Payments', 'whatsapp-menu-orders' ),
			'whatsapp' => __( 'WhatsApp', 'whatsapp-menu-orders' ),
			'design'   => __( 'Design', 'whatsapp-menu-orders' ),
			'extras'   => __( 'Extras', 'whatsapp-menu-orders' ),
		);
	}

	/**
	 * Current tab from the request.
	 *
	 * @return string
	 */
	private static function current_tab() {
		if ( '' !== self::$forced_tab ) {
			return self::$forced_tab;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$tabs = self::tabs();

		return array_key_exists( $tab, $tabs ) ? $tab : 'general';
	}

	/**
	 * Public menu page URL, when one is linked.
	 *
	 * @return string
	 */
	private static function page_url() {
		$page_id = (int) Settings::get( 'menu_page_id' );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? (string) $url : '';
	}

	/**
	 * Whether the current user may manage the plugin settings.
	 *
	 * @return bool
	 */
	private static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Print the settings screen.
	 */
	public static function render() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage these settings.', 'whatsapp-menu-orders' ) );
		}

		$tab      = self::current_tab();
		$tabs     = self::tabs();
		$page_url = self::page_url();
		?>
		<div class="wrap wfo-settings">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Food Orders settings', 'whatsapp-menu-orders' ); ?></h1>
			<?php if ( $page_url ) : ?>
				<a href="<?php echo esc_url( $page_url ); ?>" class="page-title-action" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View page', 'whatsapp-menu-orders' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['updated'] ) && '1' === sanitize_key( wp_unslash( $_GET['updated'] ) ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'whatsapp-menu-orders' ); ?></p></div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper wfo-tabs">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<?php
					$url = add_query_arg(
						array(
							'page' => 'wfo-settings',
							'tab'  => $slug,
						),
						admin_url( 'admin.php' )
					);
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo $slug === $tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wfo-settings-form">
				<input type="hidden" name="action" value="wfo_save_settings">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
				<?php wp_nonce_field( 'wfo_save_settings' ); ?>

				<?php self::render_tab( $tab ); ?>

				<?php submit_button( __( 'Save changes', 'whatsapp-menu-orders' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the Extras page, which is the settings screen with the Extras tab open.
	 */
	public static function render_extras() {
		self::$forced_tab = 'extras';
		self::render();
	}

	/**
	 * Render one tab.
	 *
	 * @param string $tab Tab slug.
	 */
	private static function render_tab( $tab ) {
		switch ( $tab ) {
			case 'ordering':
				self::tab_ordering();
				break;
			case 'payments':
				self::tab_payments();
				break;
			case 'whatsapp':
				self::tab_whatsapp();
				break;
			case 'design':
				self::tab_design();
				break;
			case 'extras':
				self::tab_extras();
				break;
			default:
				self::tab_general();
				break;
		}
	}

	/**
	 * General tab.
	 */
	private static function tab_general() {
		$currencies = array();

		foreach ( Money::currencies() as $code => $currency ) {
			$currencies[ $code ] = $currency['label'] . ' (' . $currency['symbol'] . ')';
		}

		$page_url = self::page_url();
		?>
		<table class="form-table wfo-form-table" role="presentation">
			<tbody>
				<?php
				self::row_text(
					'restaurant_name',
					__( 'Restaurant name', 'whatsapp-menu-orders' ),
					array(
						'description' => __( 'Shown in the menu header. Leave empty to use the site title.', 'whatsapp-menu-orders' ),
					)
				);

				self::row_select(
					'ui_language',
					__( 'Menu language', 'whatsapp-menu-orders' ),
					array(
						'site'  => __( 'Site language', 'whatsapp-menu-orders' ),
						'en_US' => __( 'English', 'whatsapp-menu-orders' ),
						'es_ES' => __( 'Spanish', 'whatsapp-menu-orders' ),
					),
					__( 'Applies to the public menu only. The admin keeps the site language.', 'whatsapp-menu-orders' )
				);

				self::row_select(
					'currency',
					__( 'Currency', 'whatsapp-menu-orders' ),
					$currencies,
					__( 'Money is stored in cents, so changing the currency does not convert existing prices.', 'whatsapp-menu-orders' )
				);

				self::row_select(
					'currency_position',
					__( 'Currency position', 'whatsapp-menu-orders' ),
					array(
						'left'  => __( 'Before the amount', 'whatsapp-menu-orders' ),
						'right' => __( 'After the amount', 'whatsapp-menu-orders' ),
					)
				);

				self::row_select(
					'decimals',
					__( 'Decimals', 'whatsapp-menu-orders' ),
					array(
						'0' => '0',
						'1' => '1',
						'2' => '2',
					),
					__( 'Most currencies use 2 decimals.', 'whatsapp-menu-orders' )
				);

				self::row_text(
					'thousands_sep',
					__( 'Thousands separator', 'whatsapp-menu-orders' ),
					array(
						'class'       => 'small-text',
						'maxlength'   => 1,
						'description' => __( 'For example a comma: 1,250.00.', 'whatsapp-menu-orders' ),
					)
				);

				self::row_text(
					'decimal_sep',
					__( 'Decimal separator', 'whatsapp-menu-orders' ),
					array(
						'class'       => 'small-text',
						'maxlength'   => 1,
						'description' => __( 'For example a dot: 12.50.', 'whatsapp-menu-orders' ),
					)
				);
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Public menu page', 'whatsapp-menu-orders' ); ?></th>
					<td>
						<?php if ( $page_url ) : ?>
							<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $page_url ); ?></a>
							<a class="button wfo-view-page" href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View page', 'whatsapp-menu-orders' ); ?></a>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'No ordering page is linked yet. The plugin creates one when it is activated, or you can add the [wfo_menu] shortcode to any page.', 'whatsapp-menu-orders' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Ordering tab.
	 */
	private static function tab_ordering() {
		?>
		<table class="form-table wfo-form-table" role="presentation">
			<tbody>
				<?php
				self::row_checkbox(
					'enable_pickup',
					__( 'Pickup', 'whatsapp-menu-orders' ),
					__( 'Customers can collect their order at the restaurant.', 'whatsapp-menu-orders' )
				);

				self::row_checkbox(
					'enable_delivery',
					__( 'Delivery', 'whatsapp-menu-orders' ),
					__( 'Customers can ask for their order to be delivered.', 'whatsapp-menu-orders' )
				);

				self::row_select(
					'default_order_type',
					__( 'Selected by default', 'whatsapp-menu-orders' ),
					array(
						'pickup'   => __( 'Pickup', 'whatsapp-menu-orders' ),
						'delivery' => __( 'Delivery', 'whatsapp-menu-orders' ),
					),
					__( 'The order type that is preselected in the cart. It is also shown first.', 'whatsapp-menu-orders' )
				);

				self::row_money(
					'delivery_fee',
					__( 'Delivery fee', 'whatsapp-menu-orders' ),
					__( 'Added to delivery orders. 0 = free delivery.', 'whatsapp-menu-orders' )
				);

				self::row_money(
					'free_delivery_over',
					__( 'Free delivery over', 'whatsapp-menu-orders' ),
					__( '0 = disabled. Delivery becomes free from this subtotal.', 'whatsapp-menu-orders' )
				);

				self::row_money(
					'min_order',
					__( 'Minimum order', 'whatsapp-menu-orders' ),
					__( '0 = no minimum. Orders below it cannot be sent.', 'whatsapp-menu-orders' )
				);

				self::row_select(
					'min_order_scope',
					__( 'Minimum order applies to', 'whatsapp-menu-orders' ),
					array(
						'subtotal' => __( 'Subtotal, before delivery', 'whatsapp-menu-orders' ),
						'total'    => __( 'Total, with delivery', 'whatsapp-menu-orders' ),
					)
				);

				self::row_text(
					'delivery_fee_label',
					__( 'Delivery fee label', 'whatsapp-menu-orders' ),
					array(
						'description' => __( 'Shown instead of "Free" when the delivery fee is 0, for example "By zone, cash".', 'whatsapp-menu-orders' ),
					)
				);

				self::row_textarea(
					'delivery_note',
					__( 'Delivery note', 'whatsapp-menu-orders' ),
					array(
						'rows'        => 3,
						'description' => __( 'Shown next to the delivery option, for example delivery areas or times.', 'whatsapp-menu-orders' ),
					)
				);

				self::row_text(
					'google_maps_key',
					__( 'Google Maps API key', 'whatsapp-menu-orders' ),
					array(
						'class'       => 'regular-text code',
						'maxlength'   => 80,
						'description' => __( 'Optional. With a key, delivery customers can drop a pin on a map; the WhatsApp message then includes a Google Maps link. Enable the Maps JavaScript API and restrict the key to your website.', 'whatsapp-menu-orders' ),
					)
				);

				self::row_text(
					'map_center',
					__( 'Map starts at', 'whatsapp-menu-orders' ),
					array(
						'placeholder' => '18.980899,-99.088958',
						'description' => __( 'Latitude,longitude where the map opens, usually your restaurant.', 'whatsapp-menu-orders' ),
					)
				);

				self::row_checkbox(
					'opening_hours_enabled',
					__( 'Opening hours', 'whatsapp-menu-orders' ),
					__( 'Outside these hours the menu stays visible but orders are paused.', 'whatsapp-menu-orders' )
				);
				?>
			</tbody>
		</table>

		<h2 class="wfo-settings__heading"><?php esc_html_e( 'Opening hours', 'whatsapp-menu-orders' ); ?></h2>
		<p class="description wfo-settings__intro">
			<?php esc_html_e( 'Add up to three time ranges per day. Leave a range empty when it does not apply. A closing time earlier than the opening time means the range ends after midnight.', 'whatsapp-menu-orders' ); ?>
		</p>

		<table class="widefat wfo-hours">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Day', 'whatsapp-menu-orders' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Range 1', 'whatsapp-menu-orders' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Range 2', 'whatsapp-menu-orders' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Range 3', 'whatsapp-menu-orders' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$hours = Settings::get( 'opening_hours' );
				$hours = is_array( $hours ) ? $hours : array();

				foreach ( self::days() as $day_key => $day_label ) :
					$ranges = ( isset( $hours[ $day_key ] ) && is_array( $hours[ $day_key ] ) ) ? $hours[ $day_key ] : array();
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $day_label ); ?></th>
						<?php for ( $range_index = 0; $range_index < 3; $range_index++ ) : ?>
							<?php
							$open  = isset( $ranges[ $range_index ]['open'] ) ? (string) $ranges[ $range_index ]['open'] : '';
							$close = isset( $ranges[ $range_index ]['close'] ) ? (string) $ranges[ $range_index ]['close'] : '';
							$name  = 'wfo_settings[opening_hours][' . $day_key . '][' . $range_index . ']';
							$id    = 'wfo-hours-' . $day_key . '-' . $range_index;
							/* translators: 1: weekday, 2: range number. */
							$open_label  = sprintf( __( '%1$s, range %2$d, opening time', 'whatsapp-menu-orders' ), $day_label, $range_index + 1 );
							/* translators: 1: weekday, 2: range number. */
							$close_label = sprintf( __( '%1$s, range %2$d, closing time', 'whatsapp-menu-orders' ), $day_label, $range_index + 1 );
							?>
							<td class="wfo-hours__cell">
								<input type="time" class="wfo-time" id="<?php echo esc_attr( $id . '-open' ); ?>" name="<?php echo esc_attr( $name . '[open]' ); ?>" value="<?php echo esc_attr( $open ); ?>" aria-label="<?php echo esc_attr( $open_label ); ?>">
								<span class="wfo-hours__dash" aria-hidden="true">–</span>
								<input type="time" class="wfo-time" id="<?php echo esc_attr( $id . '-close' ); ?>" name="<?php echo esc_attr( $name . '[close]' ); ?>" value="<?php echo esc_attr( $close ); ?>" aria-label="<?php echo esc_attr( $close_label ); ?>">
							</td>
						<?php endfor; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<table class="form-table wfo-form-table" role="presentation">
			<tbody>
				<?php
				self::row_textarea(
					'closed_message',
					__( 'Closed message', 'whatsapp-menu-orders' ),
					array(
						'rows'        => 3,
						'description' => __( 'Shown instead of the order form while the restaurant is closed. Leave empty for a friendly default.', 'whatsapp-menu-orders' ),
					)
				);

				self::row_checkbox(
					'show_search',
					__( 'Search box', 'whatsapp-menu-orders' ),
					__( 'Show a search field above the menu.', 'whatsapp-menu-orders' )
				);
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Payments tab.
	 */
	private static function tab_payments() {
		?>
		<table class="form-table wfo-form-table" role="presentation">
			<tbody>
				<?php
				self::row_checkbox(
					'payment_cash',
					__( 'Cash', 'whatsapp-menu-orders' ),
					__( 'Customers pay on delivery or pickup.', 'whatsapp-menu-orders' )
				);

				self::row_checkbox(
					'payment_bank',
					__( 'Bank transfer', 'whatsapp-menu-orders' ),
					__( 'Customers see your bank details in the confirmation and send the transfer separately.', 'whatsapp-menu-orders' )
				);

				self::row_textarea(
					'bank_details',
					__( 'Bank details', 'whatsapp-menu-orders' ),
					array(
						'rows'        => 6,
						'class'       => 'large-text code',
						'description' => __( 'Account holder, bank, account number or CLABE. Shown in the order confirmation.', 'whatsapp-menu-orders' ),
					)
				);

				self::row_textarea(
					'bank_note',
					__( 'Note under the bank details', 'whatsapp-menu-orders' ),
					array(
						'rows'        => 3,
						'description' => __( 'Highlighted under the bank details in the confirmation, for example that the delivery fee is paid in cash.', 'whatsapp-menu-orders' ),
					)
				);

				self::row_textarea(
					'payment_note',
					__( 'Payment note', 'whatsapp-menu-orders' ),
					array(
						'rows'        => 3,
						'description' => __( 'Extra instructions shown next to the payment options.', 'whatsapp-menu-orders' ),
					)
				);
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * WhatsApp tab.
	 */
	private static function tab_whatsapp() {
		?>
		<table class="form-table wfo-form-table" role="presentation">
			<tbody>
				<?php
				self::row_text(
					'whatsapp_number',
					__( 'WhatsApp number', 'whatsapp-menu-orders' ),
					array(
						'type'        => 'tel',
						'placeholder' => '5215512345678',
						'description' => __( 'International format, digits only. The number that receives the orders.', 'whatsapp-menu-orders' ),
					)
				);

				self::row_text(
					'whatsapp_number_2',
					__( 'Second WhatsApp number', 'whatsapp-menu-orders' ),
					array(
						'type'        => 'tel',
						'description' => __( 'Optional backup number.', 'whatsapp-menu-orders' ),
					)
				);

				self::row_text(
					'country_code',
					__( 'Country code', 'whatsapp-menu-orders' ),
					array(
						'class'       => 'small-text',
						'description' => __( 'Used to expand local numbers, for example 52 for Mexico.', 'whatsapp-menu-orders' ),
					)
				);

				self::row_text(
					'order_prefix',
					__( 'Order code prefix', 'whatsapp-menu-orders' ),
					array(
						'class'       => 'small-text',
						'maxlength'   => 8,
						'description' => __( 'Order codes look like PREFIX-XXXXX.', 'whatsapp-menu-orders' ),
					)
				);

				self::row_textarea(
					'message_template',
					__( 'WhatsApp message', 'whatsapp-menu-orders' ),
					array(
						'rows'        => 14,
						'class'       => 'large-text code',
						'description' => __( 'This is the message prepared for the customer to send. Empty lines and empty tokens are removed automatically.', 'whatsapp-menu-orders' ),
					)
				);
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Available tokens', 'whatsapp-menu-orders' ); ?></th>
					<td>
						<p class="wfo-tokens">
							<?php foreach ( self::tokens() as $token ) : ?>
								<code><?php echo esc_html( '{' . $token . '}' ); ?></code>
							<?php endforeach; ?>
						</p>
						<p class="description"><?php esc_html_e( 'Tokens are replaced with the order details. Unknown tokens are left as they are.', 'whatsapp-menu-orders' ); ?></p>
					</td>
				</tr>
				<?php
				self::row_text(
					'notify_email',
					__( 'Notification email', 'whatsapp-menu-orders' ),
					array(
						'type'        => 'email',
						'description' => __( 'New orders are emailed here. Leave empty to use the site admin email.', 'whatsapp-menu-orders' ),
					)
				);

				self::row_checkbox(
					'save_orders',
					__( 'Save orders', 'whatsapp-menu-orders' ),
					__( 'Keep a copy of every order under Food Orders → Orders. Turn off to use WhatsApp only.', 'whatsapp-menu-orders' )
				);

				self::row_text(
					'retention_days',
					__( 'Delete orders after', 'whatsapp-menu-orders' ),
					array(
						'type'        => 'number',
						'class'       => 'small-text',
						'min'         => 0,
						'max'         => 3650,
						'description' => __( 'Days. 0 = keep orders forever. Deletion runs daily and cannot be undone.', 'whatsapp-menu-orders' ),
					)
				);
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Design tab.
	 */
	private static function tab_design() {
		$accent = (string) Settings::get( 'accent' );
		$accent = $accent ? $accent : '#e2572b';
		?>
		<table class="form-table wfo-form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="wfo-accent"><?php esc_html_e( 'Accent colour', 'whatsapp-menu-orders' ); ?></label></th>
					<td>
						<input type="text" id="wfo-accent" class="wfo-color-field" name="wfo_settings[accent]" value="<?php echo esc_attr( $accent ); ?>" data-default-color="#e2572b">
						<p class="description"><?php esc_html_e( 'Used for buttons, prices, badges and highlights.', 'whatsapp-menu-orders' ); ?></p>
					</td>
				</tr>
				<?php
				self::row_text(
					'radius',
					__( 'Corner radius', 'whatsapp-menu-orders' ),
					array(
						'type'        => 'number',
						'class'       => 'small-text',
						'min'         => 0,
						'max'         => 40,
						'description' => __( 'Pixels, 0 to 40.', 'whatsapp-menu-orders' ),
					)
				);

				self::row_select(
					'dark_mode',
					__( 'Dark mode', 'whatsapp-menu-orders' ),
					array(
						'auto'  => __( 'Follow the visitor’s device', 'whatsapp-menu-orders' ),
						'light' => __( 'Always light', 'whatsapp-menu-orders' ),
						'dark'  => __( 'Always dark', 'whatsapp-menu-orders' ),
					)
				);

				self::row_textarea(
					'custom_css',
					__( 'Custom CSS', 'whatsapp-menu-orders' ),
					array(
						'rows'        => 12,
						'class'       => 'large-text code wfo-css',
						'description' => __( 'Added to the menu page only. Do not include <style> tags.', 'whatsapp-menu-orders' ),
					)
				);
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Styling reference', 'whatsapp-menu-orders' ); ?></th>
					<td>
						<p class="description">
							<?php esc_html_e( 'CSS variables on the .wfo wrapper:', 'whatsapp-menu-orders' ); ?>
							<code>.wfo { --wfo-accent, --wfo-radius, --wfo-radius-lg, --wfo-gap, --wfo-ink, --wfo-muted, --wfo-bg, --wfo-surface, --wfo-line }</code>
						</p>
						<p class="description">
							<?php esc_html_e( 'Main classes:', 'whatsapp-menu-orders' ); ?>
							<code>.wfo-card</code>, <code>.wfo-btn</code>, <code>.wfo-dialog</code>, <code>.wfo-drawer</code>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Extras tab: saved option group presets.
	 */
	private static function tab_extras() {
		?>
		<input type="hidden" name="wfo_extras_submitted" value="1">

		<h2 class="wfo-settings__heading"><?php esc_html_e( 'Extra option presets', 'whatsapp-menu-orders' ); ?></h2>
		<p class="description wfo-settings__intro">
			<?php esc_html_e( 'Build reusable groups such as "Choose a size" or "Add extras" once, then insert them into any menu item from the menu item editor.', 'whatsapp-menu-orders' ); ?>
			<?php esc_html_e( 'Editing a preset does not change items that already use it.', 'whatsapp-menu-orders' ); ?>
		</p>

		<?php Menu_Meta::render_groups_builder( 'wfo_extra_presets', Menu_Meta::presets() ); ?>
		<?php
	}

	/**
	 * Persist the settings form.
	 */
	public static function save() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage these settings.', 'whatsapp-menu-orders' ) );
		}

		check_admin_referer( 'wfo_save_settings' );

		$tab  = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'general';
		$tabs = self::tabs();
		$tab  = array_key_exists( $tab, $tabs ) ? $tab : 'general';

		if ( isset( $_POST['wfo_settings'] ) && is_array( $_POST['wfo_settings'] ) ) {
			Settings::update( wp_unslash( $_POST['wfo_settings'] ) );
		}

		if ( isset( $_POST['wfo_extras_submitted'] ) ) {
			$raw = isset( $_POST['wfo_extra_presets'] ) && is_array( $_POST['wfo_extra_presets'] ) ? wp_unslash( $_POST['wfo_extra_presets'] ) : array();

			update_option( 'wfo_extra_presets', Menu_Meta::sanitize_groups( $raw, false ) );
		}

		Orders_Admin::sync_retention();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'wfo-settings',
					'tab'     => $tab,
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Enqueue the admin assets on the settings screens.
	 */
	public static function assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! in_array( $page, array( 'wfo-settings', 'wfo-extras' ), true ) ) {
			return;
		}

		if ( ! self::can_manage() ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'wfo-admin', WFO_URL . 'assets/css/wfo-admin.css', array( 'wp-color-picker' ), WFO_VERSION );
		wp_enqueue_script( 'wfo-admin', WFO_URL . 'assets/js/wfo-admin.js', array( 'jquery', 'wp-color-picker' ), WFO_VERSION, true );

		wp_localize_script(
			'wfo-admin',
			'WFO_ADMIN',
			array(
				'i18n' => array(
					'noGroups' => __( 'No option groups yet.', 'whatsapp-menu-orders' ),
				),
			)
		);
	}

	/**
	 * Weekday labels used by the opening hours table.
	 *
	 * @return array<string,string>
	 */
	private static function days() {
		return array(
			'mon' => __( 'Monday', 'whatsapp-menu-orders' ),
			'tue' => __( 'Tuesday', 'whatsapp-menu-orders' ),
			'wed' => __( 'Wednesday', 'whatsapp-menu-orders' ),
			'thu' => __( 'Thursday', 'whatsapp-menu-orders' ),
			'fri' => __( 'Friday', 'whatsapp-menu-orders' ),
			'sat' => __( 'Saturday', 'whatsapp-menu-orders' ),
			'sun' => __( 'Sunday', 'whatsapp-menu-orders' ),
		);
	}

	/**
	 * Message template tokens.
	 *
	 * @return string[]
	 */
	private static function tokens() {
		return array( 'code', 'items', 'subtotal', 'delivery', 'total', 'payment', 'type', 'name', 'phone', 'address', 'time', 'note' );
	}

	/**
	 * A text style input row.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Field label.
	 * @param array  $args  type, class, placeholder, maxlength, min, max, description.
	 */
	private static function row_text( $key, $label, $args = array() ) {
		$type = isset( $args['type'] ) && in_array( $args['type'], array( 'text', 'email', 'tel', 'url', 'number' ), true ) ? $args['type'] : 'text';
		$class = isset( $args['class'] ) ? $args['class'] : 'regular-text';
		$value = Settings::get( $key );
		$id    = 'wfo-' . $key;
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input
					type="<?php echo esc_attr( $type ); ?>"
					id="<?php echo esc_attr( $id ); ?>"
					class="<?php echo esc_attr( $class ); ?>"
					name="wfo_settings[<?php echo esc_attr( $key ); ?>]"
					value="<?php echo esc_attr( is_scalar( $value ) ? (string) $value : '' ); ?>"
					<?php if ( ! empty( $args['placeholder'] ) ) : ?>placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"<?php endif; ?>
					<?php if ( ! empty( $args['maxlength'] ) ) : ?>maxlength="<?php echo esc_attr( (string) (int) $args['maxlength'] ); ?>"<?php endif; ?>
					<?php if ( isset( $args['min'] ) ) : ?>min="<?php echo esc_attr( (string) (int) $args['min'] ); ?>"<?php endif; ?>
					<?php if ( isset( $args['max'] ) ) : ?>max="<?php echo esc_attr( (string) (int) $args['max'] ); ?>"<?php endif; ?>
				>
				<?php self::description( isset( $args['description'] ) ? $args['description'] : '' ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * A money input row, displayed as a plain decimal string.
	 *
	 * @param string $key   Setting key (stored in minor units).
	 * @param string $label Field label.
	 * @param string $description Help text.
	 */
	private static function row_money( $key, $label, $description = '' ) {
		$value  = (int) Settings::get( $key );
		$symbol = Money::symbol( (string) Settings::get( 'currency' ) );
		$id     = 'wfo-' . $key;
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<span class="wfo-money-wrap">
					<span class="wfo-money-prefix"><?php echo esc_html( $symbol ); ?></span>
					<input type="text" id="<?php echo esc_attr( $id ); ?>" class="wfo-money-input" name="wfo_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( Money::to_input( $value ) ); ?>" inputmode="decimal" autocomplete="off" placeholder="0.00">
				</span>
				<?php self::description( $description ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * A select row.
	 *
	 * @param string $key     Setting key.
	 * @param string $label   Field label.
	 * @param array  $options value => label.
	 * @param string $description Help text.
	 */
	private static function row_select( $key, $label, $options, $description = '' ) {
		$value = (string) Settings::get( $key );
		$id    = 'wfo-' . $key;
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="<?php echo esc_attr( $id ); ?>" name="wfo_settings[<?php echo esc_attr( $key ); ?>]">
					<?php foreach ( $options as $option_value => $option_label ) : ?>
						<option value="<?php echo esc_attr( (string) $option_value ); ?>" <?php selected( $value, (string) $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php self::description( $description ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * A checkbox row with a small switch.
	 *
	 * The hidden field makes unchecked boxes post 0 so a toggle can be turned off.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Field label.
	 * @param string $description Help text.
	 */
	private static function row_checkbox( $key, $label, $description = '' ) {
		$value = (int) Settings::get( $key );
		$id    = 'wfo-' . $key;
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="hidden" name="wfo_settings[<?php echo esc_attr( $key ); ?>]" value="0">
				<label class="wfo-switch" for="<?php echo esc_attr( $id ); ?>">
					<input type="checkbox" class="wfo-switch__input" id="<?php echo esc_attr( $id ); ?>" name="wfo_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( 1, $value ); ?>>
					<span class="wfo-switch__track" aria-hidden="true"></span>
					<span class="wfo-switch__label"><?php esc_html_e( 'Enabled', 'whatsapp-menu-orders' ); ?></span>
				</label>
				<?php self::description( $description ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * A textarea row.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Field label.
	 * @param array  $args  rows, class, description.
	 */
	private static function row_textarea( $key, $label, $args = array() ) {
		$rows  = isset( $args['rows'] ) ? max( 2, (int) $args['rows'] ) : 4;
		$class = isset( $args['class'] ) ? $args['class'] : 'large-text';
		$value = Settings::get( $key );
		$id    = 'wfo-' . $key;
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<textarea id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class ); ?>" name="wfo_settings[<?php echo esc_attr( $key ); ?>]" rows="<?php echo esc_attr( (string) $rows ); ?>" cols="50"><?php echo esc_textarea( is_scalar( $value ) ? (string) $value : '' ); ?></textarea>
				<?php self::description( isset( $args['description'] ) ? $args['description'] : '' ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Help text under a field.
	 *
	 * @param string $text Help text.
	 */
	private static function description( $text ) {
		if ( '' === trim( (string) $text ) ) {
			return;
		}

		echo '<p class="description">' . esc_html( $text ) . '</p>';
	}
}
