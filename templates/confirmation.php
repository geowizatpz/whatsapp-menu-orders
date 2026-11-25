<?php
/**
 * Post-send confirmation panel.
 *
 * @package WFO
 */

defined( 'ABSPATH' ) || exit;

/** @var array $data Payload. */
/** @var array $args Render args. */

$wfo_instance = isset( $data['instance'] ) ? (string) $data['instance'] : 'wfo-1';
$wfo_strings  = isset( $data['strings'] ) && is_array( $data['strings'] ) ? $data['strings'] : array();

$wfo_text = static function ( $key, $fallback ) use ( $wfo_strings ) {
	return isset( $wfo_strings[ $key ] ) ? $wfo_strings[ $key ] : $fallback;
};

$wfo_heading_id = 'wfo-confirm-title-' . $wfo_instance;
$wfo_status_id  = 'wfo-confirm-status-' . $wfo_instance;
?>
<section class="wfo-confirm"
	data-wfo-confirm
	role="dialog"
	aria-modal="true"
	aria-labelledby="<?php echo esc_attr( $wfo_heading_id ); ?>"
	hidden>
	<div class="wfo-confirm__card">
		<p class="wfo-confirm__eyebrow"><?php echo esc_html( $wfo_text( 'orderReady', __( 'Your order', 'whatsapp-menu-orders' ) ) ); ?></p>
		<h2 class="wfo-confirm__title" id="<?php echo esc_attr( $wfo_heading_id ); ?>" data-wfo-confirm-title tabindex="-1"><?php echo esc_html( $wfo_text( 'orderSent', __( 'Order ready', 'whatsapp-menu-orders' ) ) ); ?></h2>
		<p class="wfo-confirm__hint"><?php echo esc_html( $wfo_text( 'orderSentHint', '' ) ); ?></p>

		<div class="wfo-confirm__code">
			<span class="wfo-confirm__code-label"><?php echo esc_html( $wfo_text( 'orderCode', __( 'Order code', 'whatsapp-menu-orders' ) ) ); ?></span>
			<strong class="wfo-confirm__code-value" data-wfo-code></strong>
		</div>

		<div class="wfo-confirm__totals">
			<span><?php echo esc_html( $wfo_text( 'total', __( 'Total', 'whatsapp-menu-orders' ) ) ); ?></span>
			<strong data-wfo-confirm-total></strong>
		</div>

		<div class="wfo-confirm__payment" data-wfo-confirm-payment></div>

		<div class="wfo-confirm__actions">
			<a class="wfo-btn wfo-btn--primary wfo-btn--block"
				data-wfo-wa-link
				href="#"
				target="_blank"
				rel="noopener noreferrer">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="M20 11.6a7.6 7.6 0 0 1-11.2 6.8L4 20l1.7-4.6A7.6 7.6 0 1 1 20 11.6Z"></path>
				</svg>
				<span><?php echo esc_html__( 'Open WhatsApp again', 'whatsapp-menu-orders' ); ?></span>
			</a>

			<div class="wfo-confirm__buttons">
				<button class="wfo-btn wfo-btn--ghost" type="button" data-wfo-copy><?php echo esc_html( $wfo_text( 'copyOrder', __( 'Copy order', 'whatsapp-menu-orders' ) ) ); ?></button>
				<button class="wfo-btn wfo-btn--ghost" type="button" data-wfo-restart><?php echo esc_html__( 'Start a new order', 'whatsapp-menu-orders' ); ?></button>
			</div>
		</div>

		<p class="wfo-confirm__status" id="<?php echo esc_attr( $wfo_status_id ); ?>" data-wfo-confirm-status aria-live="polite"></p>
	</div>
</section>
