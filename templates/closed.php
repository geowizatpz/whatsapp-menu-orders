<?php
/**
 * Quiet "we are closed" banner.
 *
 * @package WFO
 */

defined( 'ABSPATH' ) || exit;

/** @var array $data Payload. */
/** @var array $args Render args. */

$wfo_settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
$wfo_strings  = isset( $data['strings'] ) && is_array( $data['strings'] ) ? $data['strings'] : array();
$wfo_message  = isset( $wfo_settings['closedMessage'] ) ? trim( (string) $wfo_settings['closedMessage'] ) : '';
$wfo_next     = isset( $data['nextOpening'] ) ? trim( (string) $data['nextOpening'] ) : '';

if ( '' === $wfo_message ) {
	$wfo_message = isset( $wfo_strings['businessClosed'] ) ? $wfo_strings['businessClosed'] : __( 'We are closed right now', 'whatsapp-menu-orders' );
}
?>
<div class="wfo-closed" data-wfo-closed role="status">
	<div class="wfo-closed__body">
		<p class="wfo-closed__title"><?php echo esc_html( $wfo_message ); ?></p>
		<p class="wfo-closed__hint"><?php echo esc_html( isset( $wfo_strings['businessClosedHint'] ) ? $wfo_strings['businessClosedHint'] : '' ); ?></p>
		<?php if ( '' !== $wfo_next ) : ?>
			<p class="wfo-closed__next">
				<?php
				/* translators: %s: human readable next opening time. */
				echo esc_html( sprintf( __( 'Opens %s', 'whatsapp-menu-orders' ), $wfo_next ) );
				?>
			</p>
		<?php endif; ?>
	</div>
</div>
