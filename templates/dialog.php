<?php
/**
 * Item options dialog. Groups and options are painted by JS.
 *
 * @package WFO
 */

defined( 'ABSPATH' ) || exit;

/** @var array $data Payload. */
/** @var array $args Render args. */

$wfo_instance    = isset( $data['instance'] ) ? (string) $data['instance'] : 'wfo-1';
$wfo_strings     = isset( $data['strings'] ) && is_array( $data['strings'] ) ? $data['strings'] : array();
$wfo_dialog_id   = 'wfo-1' === $wfo_instance ? 'wfo-dialog' : 'wfo-dialog-' . $wfo_instance;
$wfo_heading_id  = 'wfo-dialog-heading-' . $wfo_instance;
$wfo_title_id    = 'wfo-dialog-title-' . $wfo_instance;
$wfo_note_id     = 'wfo-dialog-note-' . $wfo_instance;
$wfo_close_label = isset( $wfo_strings['close'] ) ? $wfo_strings['close'] : __( 'Close', 'whatsapp-menu-orders' );
?>
<dialog class="wfo-dialog"
	id="<?php echo esc_attr( $wfo_dialog_id ); ?>"
	aria-labelledby="<?php echo esc_attr( $wfo_heading_id ); ?>"
	data-wfo-dialog>
	<div class="wfo-dialog__panel">
		<span class="wfo-dialog__grab" aria-hidden="true"></span>

		<h2 class="wfo-visually-hidden" id="<?php echo esc_attr( $wfo_heading_id ); ?>"><?php echo esc_html__( 'Order options', 'whatsapp-menu-orders' ); ?></h2>

		<div class="wfo-dialog__media" data-wfo-dialog-media hidden></div>

		<button class="wfo-dialog__close"
			type="button"
			data-wfo-dialog-close
			aria-label="<?php echo esc_attr( $wfo_close_label ); ?>">
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
				<path d="M6 6l12 12M18 6L6 18"></path>
			</svg>
		</button>

		<div class="wfo-dialog__body">
			<h3 class="wfo-dialog__title" id="<?php echo esc_attr( $wfo_title_id ); ?>" data-wfo-dialog-title></h3>
			<p class="wfo-dialog__desc" data-wfo-dialog-desc hidden></p>

			<div class="wfo-dialog__groups" data-wfo-dialog-groups></div>

			<div class="wfo-field wfo-dialog__note">
				<label class="wfo-field__label" for="<?php echo esc_attr( $wfo_note_id ); ?>"><?php echo esc_html( isset( $wfo_strings['specialRequests'] ) ? $wfo_strings['specialRequests'] : __( 'Special requests', 'whatsapp-menu-orders' ) ); ?></label>
				<textarea class="wfo-field__input"
					id="<?php echo esc_attr( $wfo_note_id ); ?>"
					data-wfo-dialog-note
					rows="2"
					maxlength="200"
					placeholder="<?php echo esc_attr( isset( $wfo_strings['specialRequestsHint'] ) ? $wfo_strings['specialRequestsHint'] : '' ); ?>"></textarea>
			</div>
		</div>

		<footer class="wfo-dialog__foot">
			<div class="wfo-dialog__sum">
				<span class="wfo-dialog__sum-label"><?php echo esc_html( isset( $wfo_strings['total'] ) ? $wfo_strings['total'] : __( 'Total', 'whatsapp-menu-orders' ) ); ?></span>
				<span class="wfo-dialog__total" data-wfo-dialog-total aria-live="polite"></span>
			</div>

			<div class="wfo-dialog__controls">
				<div class="wfo-stepper wfo-qty" role="group" aria-label="<?php echo esc_attr( isset( $wfo_strings['quantity'] ) ? $wfo_strings['quantity'] : __( 'Quantity', 'whatsapp-menu-orders' ) ); ?>">
					<button class="wfo-stepper__btn" type="button" data-wfo-qty-minus aria-label="<?php echo esc_attr( '− ' . ( isset( $wfo_strings['quantity'] ) ? $wfo_strings['quantity'] : __( 'Quantity', 'whatsapp-menu-orders' ) ) ); ?>">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
							<path d="M6 12h12"></path>
						</svg>
					</button>
					<input class="wfo-stepper__input"
						data-wfo-qty-input
						type="text"
						inputmode="numeric"
						pattern="[0-9]*"
						value="1"
						maxlength="2"
						aria-label="<?php echo esc_attr( isset( $wfo_strings['quantity'] ) ? $wfo_strings['quantity'] : __( 'Quantity', 'whatsapp-menu-orders' ) ); ?>">
					<button class="wfo-stepper__btn" type="button" data-wfo-qty-plus aria-label="<?php echo esc_attr( '+ ' . ( isset( $wfo_strings['quantity'] ) ? $wfo_strings['quantity'] : __( 'Quantity', 'whatsapp-menu-orders' ) ) ); ?>">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
							<path d="M12 6v12M6 12h12"></path>
						</svg>
					</button>
				</div>

				<button class="wfo-btn wfo-btn--primary wfo-dialog__add" type="button" data-wfo-dialog-add>
					<span data-wfo-dialog-add-label><?php echo esc_html( isset( $wfo_strings['addToOrder'] ) ? $wfo_strings['addToOrder'] : __( 'Add to order', 'whatsapp-menu-orders' ) ); ?></span>
				</button>
			</div>

			<p class="wfo-dialog__hint" data-wfo-dialog-hint aria-live="polite"></p>
		</footer>
	</div>
</dialog>
