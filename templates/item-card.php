<?php
/**
 * One menu card.
 *
 * @package WFO
 */

defined( 'ABSPATH' ) || exit;

/** @var array $data Payload. */
/** @var array $args Render args, including the current 'item'. */

$wfo_item = isset( $args['item'] ) && is_array( $args['item'] ) ? $args['item'] : null;

if ( ! $wfo_item || empty( $wfo_item['id'] ) ) {
	return;
}

$wfo_strings     = isset( $data['strings'] ) && is_array( $data['strings'] ) ? $data['strings'] : array();
$wfo_id          = (int) $wfo_item['id'];
$wfo_name        = isset( $wfo_item['name'] ) ? (string) $wfo_item['name'] : '';
$wfo_description = isset( $wfo_item['description'] ) ? (string) $wfo_item['description'] : '';
$wfo_price       = isset( $wfo_item['price'] ) ? (int) $wfo_item['price'] : 0;
$wfo_compare     = isset( $wfo_item['compare_price'] ) ? (int) $wfo_item['compare_price'] : 0;
$wfo_available   = ! isset( $wfo_item['available'] ) || ! empty( $wfo_item['available'] );
$wfo_badges      = isset( $wfo_item['badges'] ) && is_array( $wfo_item['badges'] ) ? $wfo_item['badges'] : array();
$wfo_image       = isset( $wfo_item['image'] ) && is_array( $wfo_item['image'] ) ? $wfo_item['image'] : null;
$wfo_show_images = ! isset( $args['show_images'] ) || $args['show_images'];
$wfo_show_desc   = ! isset( $args['show_description'] ) || $args['show_description'];
$wfo_soldout     = isset( $wfo_strings['soldOut'] ) ? $wfo_strings['soldOut'] : __( 'Sold out', 'whatsapp-menu-orders' );
$wfo_image_alt   = '';

if ( $wfo_image && ! empty( $wfo_image['alt'] ) ) {
	$wfo_image_alt = (string) $wfo_image['alt'];
}

if ( '' === trim( $wfo_image_alt ) ) {
	$wfo_image_alt = $wfo_name;
}

/* translators: %s: menu item name. */
$wfo_button_label = sprintf( __( 'Add %s to order', 'whatsapp-menu-orders' ), $wfo_name );

if ( ! $wfo_available ) {
	$wfo_button_label = $wfo_name . ' — ' . $wfo_soldout;
}

$wfo_badge_html = '';

foreach ( $wfo_badges as $wfo_badge ) {
	$wfo_badge_label = isset( $wfo_badge['label'] ) ? (string) $wfo_badge['label'] : '';
	$wfo_badge_tone  = isset( $wfo_badge['tone'] ) ? (string) $wfo_badge['tone'] : 'default';

	if ( '' === trim( $wfo_badge_label ) ) {
		continue;
	}

	if ( ! in_array( $wfo_badge_tone, array( 'hot', 'new', 'veg', 'default' ), true ) ) {
		$wfo_badge_tone = 'default';
	}

	$wfo_badge_html .= '<span class="wfo-badge wfo-badge--' . esc_attr( $wfo_badge_tone ) . '">' . esc_html( $wfo_badge_label ) . '</span>';
}

if ( ! $wfo_available ) {
	$wfo_badge_html .= '<span class="wfo-badge wfo-badge--soldout">' . esc_html( $wfo_soldout ) . '</span>';
}

$wfo_has_media = $wfo_show_images;
?>
<article class="wfo-card<?php echo $wfo_available ? '' : ' is-soldout'; ?>"
	data-wfo-item="<?php echo esc_attr( (string) $wfo_id ); ?>"
	data-wfo-available="<?php echo $wfo_available ? '1' : '0'; ?>">

	<?php if ( $wfo_has_media ) : ?>
		<div class="wfo-card__media">
			<?php if ( $wfo_image && ! empty( $wfo_image['url'] ) ) : ?>
				<?php
				$wfo_img_width  = ! empty( $wfo_image['width'] ) ? (int) $wfo_image['width'] : 800;
				$wfo_img_height = ! empty( $wfo_image['height'] ) ? (int) $wfo_image['height'] : 600;
				?>
				<img class="wfo-card__img"
					src="<?php echo esc_url( $wfo_image['url'] ); ?>"
					<?php if ( ! empty( $wfo_image['srcset'] ) ) : ?>
						srcset="<?php echo esc_attr( $wfo_image['srcset'] ); ?>"
						sizes="(min-width: 960px) 33vw, (min-width: 560px) 50vw, 100vw"
					<?php endif; ?>
					alt="<?php echo esc_attr( $wfo_image_alt ); ?>"
					width="<?php echo esc_attr( (string) $wfo_img_width ); ?>"
					height="<?php echo esc_attr( (string) $wfo_img_height ); ?>"
					loading="lazy"
					decoding="async">
			<?php else : ?>
				<span class="wfo-card__monogram" aria-hidden="true"><?php echo esc_html( mb_substr( $wfo_name, 0, 1 ) ); ?></span>
			<?php endif; ?>

			<?php if ( '' !== $wfo_badge_html ) : ?>
				<div class="wfo-card__badges"><?php echo $wfo_badge_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="wfo-card__body">
		<?php if ( ! $wfo_has_media && '' !== $wfo_badge_html ) : ?>
			<div class="wfo-card__badges wfo-card__badges--inline"><?php echo $wfo_badge_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?></div>
		<?php endif; ?>

		<h3 class="wfo-card__title"><?php echo esc_html( $wfo_name ); ?></h3>

		<?php if ( $wfo_show_desc && '' !== trim( $wfo_description ) ) : ?>
			<p class="wfo-card__desc"><?php echo esc_html( $wfo_description ); ?></p>
		<?php endif; ?>

		<div class="wfo-card__foot">
			<span class="wfo-price">
				<span class="wfo-price__now"><?php echo esc_html( \WFO\Render::money( $wfo_price ) ); ?></span>
				<?php if ( $wfo_compare > $wfo_price ) : ?>
					<s class="wfo-price__compare is-compare"><?php echo esc_html( \WFO\Render::money( $wfo_compare ) ); ?></s>
				<?php endif; ?>
			</span>
			<span class="wfo-card__add" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" focusable="false">
					<path d="M12 6v12M6 12h12"></path>
				</svg>
			</span>
		</div>
	</div>

	<button class="wfo-card__hit"
		type="button"
		data-wfo-open="<?php echo esc_attr( (string) $wfo_id ); ?>"
		aria-label="<?php echo esc_attr( $wfo_button_label ); ?>"
		<?php disabled( ! $wfo_available ); ?>></button>
</article>
