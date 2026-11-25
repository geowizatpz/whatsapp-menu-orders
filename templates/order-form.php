<?php
/**
 * Customer details form. Included inside the cart drawer form.
 *
 * @package WFO
 */

defined( 'ABSPATH' ) || exit;

/** @var array $data Payload. */
/** @var array $args Render args. */

$wfo_instance = isset( $data['instance'] ) ? (string) $data['instance'] : 'wfo-1';
$wfo_settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
$wfo_strings  = isset( $data['strings'] ) && is_array( $data['strings'] ) ? $data['strings'] : array();
$wfo_prefix   = 'wfo-' . $wfo_instance . '-';

$wfo_text = static function ( $key, $fallback ) use ( $wfo_strings ) {
	return isset( $wfo_strings[ $key ] ) ? $wfo_strings[ $key ] : $fallback;
};

$wfo_enable_pickup   = ! empty( $wfo_settings['enablePickup'] );
$wfo_enable_delivery = ! empty( $wfo_settings['enableDelivery'] );
$wfo_default_type    = $wfo_enable_pickup ? 'pickup' : ( $wfo_enable_delivery ? 'delivery' : '' );

if ( $wfo_enable_delivery && isset( $wfo_settings['defaultType'] ) && 'delivery' === $wfo_settings['defaultType'] ) {
	$wfo_default_type = 'delivery';
}

$wfo_maps_key = isset( $wfo_settings['mapsKey'] ) ? (string) $wfo_settings['mapsKey'] : '';

/* Order type choices, the preselected one first. */
$wfo_types = array();

if ( $wfo_enable_pickup ) {
	$wfo_types['pickup'] = $wfo_text( 'pickup', __( 'Pickup', 'whatsapp-menu-orders' ) );
}

if ( $wfo_enable_delivery ) {
	$wfo_types['delivery'] = $wfo_text( 'delivery_', __( 'Delivery', 'whatsapp-menu-orders' ) );
}

if ( 'delivery' === $wfo_default_type && isset( $wfo_types['pickup'] ) ) {
	$wfo_types = array_merge( array( 'delivery' => $wfo_types['delivery'] ), $wfo_types );
}

$wfo_payments = array();

if ( ! empty( $wfo_settings['paymentCash'] ) ) {
	$wfo_payments['cash'] = array(
		'label' => $wfo_text( 'cash', __( 'Cash', 'whatsapp-menu-orders' ) ),
		'hint'  => $wfo_text( 'cashHint', __( 'Pay on delivery or pickup.', 'whatsapp-menu-orders' ) ),
	);
}

if ( ! empty( $wfo_settings['paymentBank'] ) ) {
	$wfo_payments['bank'] = array(
		'label' => $wfo_text( 'bank', __( 'Bank transfer', 'whatsapp-menu-orders' ) ),
		'hint'  => $wfo_text( 'bankHint', __( 'We will send the account details on WhatsApp.', 'whatsapp-menu-orders' ) ),
	);
}

$wfo_default_payment = 1 === count( $wfo_payments ) ? key( $wfo_payments ) : '';

/* 30-minute pickup/delivery slots for the next four hours, in site time. */
$wfo_slots = array();

if ( function_exists( 'current_datetime' ) ) {
	$wfo_now     = current_datetime();
	$wfo_minutes = ( (int) $wfo_now->format( 'G' ) * 60 ) + (int) $wfo_now->format( 'i' );
	$wfo_start   = (int) ( ceil( ( $wfo_minutes + 1 ) / 30 ) * 30 );

	for ( $wfo_i = 0; $wfo_i < 8; $wfo_i++ ) {
		$wfo_offset = max( 1, $wfo_start + ( 30 * $wfo_i ) - $wfo_minutes );
		$wfo_slot   = $wfo_now->modify( '+' . $wfo_offset . ' minutes' );

		$wfo_slots[] = array(
			'value' => wp_date( 'Y-m-d H:i', $wfo_slot->getTimestamp() ),
			/* translators: 1: short weekday, 2: time format from Settings > General. */
			'label' => wp_date( 'D, ' . get_option( 'time_format' ), $wfo_slot->getTimestamp() ),
		);
	}
}
?>
<div class="wfo-form__inner">
	<h3 class="wfo-form__title"><?php echo esc_html( $wfo_text( 'yourDetails', __( 'Your details', 'whatsapp-menu-orders' ) ) ); ?></h3>

	<div class="wfo-field" data-wfo-field="name">
		<label class="wfo-field__label" for="<?php echo esc_attr( $wfo_prefix . 'name' ); ?>"><?php echo esc_html( $wfo_text( 'name', __( 'Name', 'whatsapp-menu-orders' ) ) ); ?></label>
		<input class="wfo-field__input"
			id="<?php echo esc_attr( $wfo_prefix . 'name' ); ?>"
			type="text"
			name="customer[name]"
			autocomplete="name"
			maxlength="60"
			aria-describedby="<?php echo esc_attr( $wfo_prefix . 'err-name' ); ?>">
		<span class="wfo-field__error" id="<?php echo esc_attr( $wfo_prefix . 'err-name' ); ?>" data-wfo-error="name" aria-live="polite"></span>
	</div>

	<div class="wfo-field" data-wfo-field="phone">
		<label class="wfo-field__label" for="<?php echo esc_attr( $wfo_prefix . 'phone' ); ?>"><?php echo esc_html( $wfo_text( 'phone', __( 'Phone', 'whatsapp-menu-orders' ) ) ); ?></label>
		<input class="wfo-field__input"
			id="<?php echo esc_attr( $wfo_prefix . 'phone' ); ?>"
			type="tel"
			name="customer[phone]"
			autocomplete="tel"
			inputmode="tel"
			maxlength="24"
			aria-describedby="<?php echo esc_attr( $wfo_prefix . 'err-phone' ); ?>">
		<span class="wfo-field__error" id="<?php echo esc_attr( $wfo_prefix . 'err-phone' ); ?>" data-wfo-error="phone" aria-live="polite"></span>
	</div>

	<?php if ( $wfo_enable_pickup || $wfo_enable_delivery ) : ?>
		<fieldset class="wfo-fieldset" data-wfo-field="type" aria-describedby="<?php echo esc_attr( $wfo_prefix . 'err-type' ); ?>">
			<legend class="wfo-field__label"><?php echo esc_html( $wfo_text( 'orderType', __( 'Order type', 'whatsapp-menu-orders' ) ) ); ?></legend>
			<div class="wfo-segment">
				<?php foreach ( $wfo_types as $wfo_type_value => $wfo_type_label ) : ?>
					<label class="wfo-segment__item<?php echo $wfo_type_value === $wfo_default_type ? ' is-checked' : ''; ?>">
						<input type="radio" name="type" value="<?php echo esc_attr( $wfo_type_value ); ?>" <?php checked( $wfo_type_value, $wfo_default_type ); ?>>
						<span><?php echo esc_html( $wfo_type_label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<span class="wfo-field__error" id="<?php echo esc_attr( $wfo_prefix . 'err-type' ); ?>" data-wfo-error="type" aria-live="polite"></span>
		</fieldset>
	<?php else : ?>
		<p class="wfo-form__notice"><?php echo esc_html( $wfo_text( 'notAvailable', __( 'Ordering is not available right now.', 'whatsapp-menu-orders' ) ) ); ?></p>
	<?php endif; ?>

	<?php if ( $wfo_enable_delivery && '' !== trim( (string) ( isset( $wfo_settings['deliveryNote'] ) ? $wfo_settings['deliveryNote'] : '' ) ) ) : ?>
		<?php
		$wfo_note_lines = preg_split( '/\r\n|\r|\n/', trim( (string) $wfo_settings['deliveryNote'] ), 2 );
		?>
		<p class="wfo-form__note wfo-form__note--delivery" data-wfo-delivery-note hidden><strong><?php echo esc_html( $wfo_note_lines[0] ); ?></strong><?php echo isset( $wfo_note_lines[1] ) ? "\n" . esc_html( $wfo_note_lines[1] ) : ''; ?></p>
	<?php endif; ?>

	<div class="wfo-field" data-wfo-field="address" data-wfo-address <?php echo 'delivery' === $wfo_default_type ? '' : 'hidden'; ?>>
		<?php if ( '' !== $wfo_maps_key ) : ?>
			<div class="wfo-map" data-wfo-map hidden>
				<p class="wfo-map__hint"><?php echo esc_html( $wfo_text( 'mapHint', __( 'Optional: tap the map or drag the pin to your door.', 'whatsapp-menu-orders' ) ) ); ?></p>
				<div class="wfo-map__frame">
					<div class="wfo-map__canvas" data-wfo-map-canvas role="application" aria-label="<?php echo esc_attr( $wfo_text( 'mapLabel', __( 'Map to mark your delivery location', 'whatsapp-menu-orders' ) ) ); ?>"></div>
					<button type="button" class="wfo-map__btn wfo-map__btn--locate" data-wfo-map-locate><?php echo esc_html( $wfo_text( 'mapLocate', __( 'Use my location', 'whatsapp-menu-orders' ) ) ); ?></button>
				</div>
				<div class="wfo-map__bar">
					<button type="button" class="wfo-map__btn wfo-map__btn--ghost" data-wfo-map-clear hidden><?php echo esc_html( $wfo_text( 'mapClear', __( 'Remove pin', 'whatsapp-menu-orders' ) ) ); ?></button>
				</div>
				<p class="wfo-map__status" data-wfo-map-status aria-live="polite"></p>
				<input type="hidden" name="customer[location]" value="" data-wfo-location>
			</div>
		<?php endif; ?>
		<label class="wfo-field__label" for="<?php echo esc_attr( $wfo_prefix . 'address' ); ?>"><?php echo esc_html( $wfo_text( 'address', __( 'Address', 'whatsapp-menu-orders' ) ) ); ?></label>
		<textarea class="wfo-field__input"
			id="<?php echo esc_attr( $wfo_prefix . 'address' ); ?>"
			name="customer[address]"
			autocomplete="street-address"
			rows="2"
			maxlength="200"
			placeholder="<?php echo esc_attr( $wfo_text( 'addressPlaceholder', '' ) ); ?>"
			aria-describedby="<?php echo esc_attr( $wfo_prefix . 'err-address' ); ?>"></textarea>
		<span class="wfo-field__error" id="<?php echo esc_attr( $wfo_prefix . 'err-address' ); ?>" data-wfo-error="address" aria-live="polite"></span>
	</div>

	<div class="wfo-field" data-wfo-field="time">
		<label class="wfo-field__label" for="<?php echo esc_attr( $wfo_prefix . 'time' ); ?>"><?php echo esc_html( $wfo_text( 'when', __( 'When', 'whatsapp-menu-orders' ) ) ); ?></label>
		<select class="wfo-field__input" id="<?php echo esc_attr( $wfo_prefix . 'time' ); ?>" name="customer[time]">
			<option value="asap"><?php echo esc_html( $wfo_text( 'asap', __( 'As soon as possible', 'whatsapp-menu-orders' ) ) ); ?></option>
			<?php if ( $wfo_slots ) : ?>
				<optgroup label="<?php echo esc_attr( $wfo_text( 'scheduled', __( 'Schedule', 'whatsapp-menu-orders' ) ) ); ?>">
					<?php foreach ( $wfo_slots as $wfo_slot ) : ?>
						<option value="<?php echo esc_attr( $wfo_slot['value'] ); ?>"><?php echo esc_html( $wfo_slot['label'] ); ?></option>
					<?php endforeach; ?>
				</optgroup>
			<?php endif; ?>
		</select>
	</div>

	<?php if ( $wfo_payments ) : ?>
		<fieldset class="wfo-fieldset" data-wfo-field="payment" aria-describedby="<?php echo esc_attr( $wfo_prefix . 'err-payment' ); ?>">
			<legend class="wfo-field__label"><?php echo esc_html( $wfo_text( 'payment', __( 'Payment', 'whatsapp-menu-orders' ) ) ); ?></legend>
			<div class="wfo-pay">
				<?php foreach ( $wfo_payments as $wfo_pay_key => $wfo_pay ) : ?>
					<label class="wfo-pay__card<?php echo $wfo_pay_key === $wfo_default_payment ? ' is-checked' : ''; ?>">
						<input type="radio" name="payment" value="<?php echo esc_attr( $wfo_pay_key ); ?>" <?php checked( $wfo_pay_key, $wfo_default_payment ); ?>>
						<span class="wfo-pay__text">
							<strong><?php echo esc_html( $wfo_pay['label'] ); ?></strong>
							<small><?php echo esc_html( $wfo_pay['hint'] ); ?></small>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
			<span class="wfo-field__error" id="<?php echo esc_attr( $wfo_prefix . 'err-payment' ); ?>" data-wfo-error="payment" aria-live="polite"></span>
		</fieldset>
	<?php else : ?>
		<p class="wfo-form__notice"><?php echo esc_html( $wfo_text( 'choosePayment', __( 'Choose how you would like to pay.', 'whatsapp-menu-orders' ) ) ); ?></p>
	<?php endif; ?>

	<?php if ( '' !== trim( (string) ( isset( $wfo_settings['paymentNote'] ) ? $wfo_settings['paymentNote'] : '' ) ) ) : ?>
		<p class="wfo-form__note"><?php echo esc_html( $wfo_settings['paymentNote'] ); ?></p>
	<?php endif; ?>

	<div class="wfo-field" data-wfo-field="note">
		<label class="wfo-field__label" for="<?php echo esc_attr( $wfo_prefix . 'note' ); ?>"><?php echo esc_html( $wfo_text( 'note', __( 'Note for the restaurant', 'whatsapp-menu-orders' ) ) ); ?></label>
		<textarea class="wfo-field__input"
			id="<?php echo esc_attr( $wfo_prefix . 'note' ); ?>"
			name="customer[note]"
			rows="2"
			maxlength="400"
			placeholder="<?php echo esc_attr( $wfo_text( 'notePlaceholder', '' ) ); ?>"></textarea>
	</div>
</div>
