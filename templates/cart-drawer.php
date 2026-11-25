<?php
/**
 * Cart drawer with line items, totals and the customer form.
 *
 * @package WFO
 */

defined( 'ABSPATH' ) || exit;

/** @var array $data Payload. */
/** @var array $args Render args. */

$wfo_instance  = isset( $data['instance'] ) ? (string) $data['instance'] : 'wfo-1';
$wfo_settings  = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
$wfo_strings   = isset( $data['strings'] ) && is_array( $data['strings'] ) ? $data['strings'] : array();
$wfo_number    = isset( $wfo_settings['whatsappNumber'] ) ? preg_replace( '/\D+/', '', (string) $wfo_settings['whatsappNumber'] ) : '';
$wfo_whatsapp  = ! empty( $wfo_settings['whatsapp'] ) && '' !== $wfo_number;
$wfo_drawer_id = 'wfo-drawer-' . $wfo_instance;
$wfo_form_id   = 'wfo-form-' . $wfo_instance;
$wfo_title_id  = 'wfo-drawer-title-' . $wfo_instance;

$wfo_text = static function ( $key, $fallback ) use ( $wfo_strings ) {
	return isset( $wfo_strings[ $key ] ) ? $wfo_strings[ $key ] : $fallback;
};
?>
<div class="wfo-drawer-backdrop" data-wfo-drawer-backdrop hidden></div>

<aside class="wfo-drawer is-empty"
	id="<?php echo esc_attr( $wfo_drawer_id ); ?>"
	data-wfo-drawer
	aria-hidden="true"
	role="dialog"
	aria-modal="true"
	aria-labelledby="<?php echo esc_attr( $wfo_title_id ); ?>">
	<div class="wfo-drawer__panel">
		<header class="wfo-drawer__head">
			<div class="wfo-drawer__heading">
				<h2 class="wfo-drawer__title" id="<?php echo esc_attr( $wfo_title_id ); ?>"><?php echo esc_html( $wfo_text( 'yourOrder', __( 'Your order', 'whatsapp-menu-orders' ) ) ); ?></h2>
				<p class="wfo-drawer__count" data-wfo-drawer-count></p>
			</div>
			<button class="wfo-drawer__close" type="button" data-wfo-drawer-close aria-label="<?php echo esc_attr( $wfo_text( 'close', __( 'Close', 'whatsapp-menu-orders' ) ) ); ?>">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
					<path d="M6 6l12 12M18 6L6 18"></path>
				</svg>
			</button>
		</header>

		<form class="wfo-form" id="<?php echo esc_attr( $wfo_form_id ); ?>" data-wfo-form novalidate>
			<div class="wfo-drawer__body">
				<div class="wfo-empty" data-wfo-empty>
					<svg class="wfo-empty__icon" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
						<path d="M5.5 8.5h13l-1.2 11.2a1 1 0 0 1-1 .8H7.7a1 1 0 0 1-1-.8L5.5 8.5Z"></path>
						<path d="M9 8.5V6.8a3 3 0 0 1 6 0v1.7"></path>
					</svg>
					<p class="wfo-empty__title"><?php echo esc_html( $wfo_text( 'emptyCart', __( 'Your order is empty.', 'whatsapp-menu-orders' ) ) ); ?></p>
					<p class="wfo-empty__hint"><?php echo esc_html( $wfo_text( 'emptyCartHint', '' ) ); ?></p>
					<button class="wfo-btn wfo-btn--ghost" type="button" data-wfo-drawer-close><?php echo esc_html( $wfo_text( 'continue', __( 'Keep browsing', 'whatsapp-menu-orders' ) ) ); ?></button>
				</div>

				<ul class="wfo-lines" data-wfo-lines></ul>

				<div class="wfo-summary wfo-totals" data-wfo-summary>
					<div class="wfo-summary__row">
						<span><?php echo esc_html( $wfo_text( 'subtotal', __( 'Subtotal', 'whatsapp-menu-orders' ) ) ); ?></span>
						<span data-wfo-subtotal>—</span>
					</div>
					<div class="wfo-summary__row" data-wfo-delivery-row hidden>
						<span><?php echo esc_html( $wfo_text( 'delivery', __( 'Delivery', 'whatsapp-menu-orders' ) ) ); ?></span>
						<span data-wfo-delivery>—</span>
					</div>
					<p class="wfo-summary__hint" data-wfo-free-hint hidden></p>
					<div class="wfo-summary__row wfo-summary__row--total">
						<span><?php echo esc_html( $wfo_text( 'total', __( 'Total', 'whatsapp-menu-orders' ) ) ); ?></span>
						<span data-wfo-total>—</span>
					</div>
					<p class="wfo-summary__notice" data-wfo-min-note aria-live="polite" hidden></p>
				</div>

				<?php echo \WFO\Render::template( 'order-form', $data, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output is escaped internally. ?>
			</div>

			<footer class="wfo-drawer__foot">
				<p class="wfo-drawer__notice" data-wfo-no-whatsapp <?php echo $wfo_whatsapp ? 'hidden' : ''; ?>>
					<?php echo esc_html( $wfo_text( 'whatsappMissing', __( 'WhatsApp number is not configured yet.', 'whatsapp-menu-orders' ) ) ); ?>
				</p>

				<button class="wfo-btn wfo-btn--primary wfo-btn--block wfo-send"
					type="submit"
					data-wfo-send
					<?php disabled( ! $wfo_whatsapp ); ?>>
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
						<path d="M20 11.6a7.6 7.6 0 0 1-11.2 6.8L4 20l1.7-4.6A7.6 7.6 0 1 1 20 11.6Z"></path>
					</svg>
					<span data-wfo-send-label><?php echo esc_html( $wfo_text( 'sendOnWhatsApp', __( 'Send order on WhatsApp', 'whatsapp-menu-orders' ) ) ); ?></span>
				</button>

				<p class="wfo-send__hint" data-wfo-send-hint aria-live="polite">
					<?php echo esc_html( $wfo_whatsapp ? $wfo_text( 'sendingHint', '' ) : $wfo_text( 'whatsappMissing', '' ) ); ?>
				</p>
				<p class="wfo-send__error" data-wfo-form-error role="alert" hidden></p>
			</footer>
		</form>
	</div>
</aside>
