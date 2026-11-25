<?php
/**
 * Plugin Name:       WhatsApp Menu & Orders
 * Plugin URI:        https://github.com/geowizatpz/whatsapp-menu-orders
 * Description:       A one-page food menu with extras and per-item notes that sends the finished order to WhatsApp. No checkout, no payment gateway: cash or bank transfer, agreed in the chat.
 * Version:           0.2.2
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Local Food Delivery
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       whatsapp-menu-orders
 * Domain Path:       /languages
 *
 * @package WFO
 */

defined( 'ABSPATH' ) || exit;

define( 'WFO_VERSION', '0.2.2' );
define( 'WFO_FILE', __FILE__ );
define( 'WFO_DIR', plugin_dir_path( __FILE__ ) );
define( 'WFO_URL', plugin_dir_url( __FILE__ ) );
define( 'WFO_TD', 'whatsapp-menu-orders' );
define( 'WFO_SLUG', 'whatsapp-menu-orders' );
define( 'WFO_CPT_ITEM', 'wfo_item' );
define( 'WFO_CPT_ORDER', 'wfo_order' );
define( 'WFO_TAX_CAT', 'wfo_category' );
define( 'WFO_OPTION', 'wfo_settings' );

require_once WFO_DIR . 'includes/class-autoloader.php';
WFO\Autoloader::register();

register_activation_hook( __FILE__, array( 'WFO\Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WFO\Install', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		WFO\Plugin::instance()->boot();
	}
);
