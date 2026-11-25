<?php
/**
 * Menu app shell: wrapper, inline payload, catbar, sections and partials.
 *
 * @package WFO
 */

defined( 'ABSPATH' ) || exit;

/** @var array $data Payload from WFO\Menu_Repository::payload(). */
/** @var array $args Render args from WFO\Render::menu(). */

$wfo_instance    = isset( $data['instance'] ) ? (string) $data['instance'] : 'wfo-1';
$wfo_settings    = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
$wfo_strings     = isset( $data['strings'] ) && is_array( $data['strings'] ) ? $data['strings'] : array();
$wfo_items       = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
$wfo_cats        = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
$wfo_columns     = isset( $args['columns'] ) ? max( 1, min( 4, (int) $args['columns'] ) ) : 3;
$wfo_layout      = ( isset( $args['layout'] ) && 'list' === $args['layout'] ) ? 'list' : 'grid';
$wfo_show_images = ! isset( $args['show_images'] ) || $args['show_images'];
$wfo_show_desc   = ! isset( $args['show_description'] ) || $args['show_description'];
$wfo_sticky_cart = ! isset( $args['sticky_cart'] ) || $args['sticky_cart'];
$wfo_extra_class = isset( $args['class'] ) ? trim( (string) $args['class'] ) : '';
$wfo_is_open     = ! empty( $data['open'] );
$wfo_whatsapp    = ! empty( $wfo_settings['whatsapp'] );
$wfo_show_search = ! empty( $wfo_settings['showSearch'] );

/*
 * Dark mode is a design setting rather than part of the public payload, so read it
 * here (and allow $args['dark'] to override it for Elementor / theme integrations).
 * The attribute lets CSS suppress the prefers-color-scheme block when light is forced.
 */
$wfo_dark = isset( $args['dark'] ) ? (string) $args['dark'] : '';
if ( '' === $wfo_dark && isset( $wfo_settings['darkMode'] ) ) {
	$wfo_dark = (string) $wfo_settings['darkMode'];
}
if ( '' === $wfo_dark && class_exists( 'WFO\\Settings' ) ) {
	$wfo_dark = (string) \WFO\Settings::get( 'dark_mode', 'auto' );
}
if ( ! in_array( $wfo_dark, array( 'light', 'dark' ), true ) ) {
	$wfo_dark = '';
}

$wfo_dialog_id = 'wfo-1' === $wfo_instance ? 'wfo-dialog' : 'wfo-dialog-' . $wfo_instance;
$wfo_drawer_id = 'wfo-drawer-' . $wfo_instance;
$wfo_search_id = 'wfo-search-' . $wfo_instance;
$wfo_head_id   = 'wfo-dialog-heading-' . $wfo_instance;
$wfo_form_id   = 'wfo-form-' . $wfo_instance;
$wfo_total_id  = 'wfo-cart-total-' . $wfo_instance;

/* Bucket items by category term id. */
$wfo_by_cat  = array();
$wfo_uncat   = array();

foreach ( $wfo_items as $wfo_item ) {
	$wfo_item_cats = isset( $wfo_item['categories'] ) ? (array) $wfo_item['categories'] : array();
	$wfo_placed    = false;

	foreach ( $wfo_item_cats as $wfo_term_id ) {
		$wfo_by_cat[ (int) $wfo_term_id ][] = $wfo_item;
		$wfo_placed                          = true;
	}

	if ( ! $wfo_placed ) {
		$wfo_uncat[] = $wfo_item;
	}
}

$wfo_sections = array();
$wfo_used     = array();

foreach ( $wfo_cats as $wfo_cat ) {
	$wfo_term_id  = isset( $wfo_cat['id'] ) ? (int) $wfo_cat['id'] : 0;
	$wfo_cat_items = isset( $wfo_by_cat[ $wfo_term_id ] ) ? $wfo_by_cat[ $wfo_term_id ] : array();

	if ( ! $wfo_cat_items ) {
		continue;
	}

	$wfo_slug     = isset( $wfo_cat['slug'] ) ? (string) $wfo_cat['slug'] : 'cat-' . $wfo_term_id;
	$wfo_used[]   = $wfo_slug;
	$wfo_sections[] = array(
		'slug'        => $wfo_slug,
		'name'        => isset( $wfo_cat['name'] ) ? (string) $wfo_cat['name'] : '',
		'description' => isset( $wfo_cat['description'] ) ? (string) $wfo_cat['description'] : '',
		'items'       => $wfo_cat_items,
	);
}

/* Items without a term still deserve a section instead of silently disappearing. */
if ( $wfo_uncat ) {
	$wfo_slug = 'more';
	while ( in_array( $wfo_slug, $wfo_used, true ) ) {
		$wfo_slug .= '-more';
	}

	$wfo_sections[] = array(
		'slug'        => $wfo_slug,
		'name'        => __( 'More', 'whatsapp-menu-orders' ),
		'description' => '',
		'items'       => $wfo_uncat,
	);
}

$wfo_show_catbar = ! empty( $args['show_catbar'] ) && count( $wfo_sections ) > 1;

$wfo_root_class = 'wfo' . ( $wfo_is_open ? '' : ' wfo--closed' ) . ( $wfo_extra_class ? ' ' . $wfo_extra_class : '' );
$wfo_root_style = '--wfo-cols:' . (int) $wfo_columns;

$wfo_json_data = $data;
unset( $wfo_json_data['args'] );
?>
<div class="<?php echo esc_attr( $wfo_root_class ); ?>"
	data-wfo-root
	data-wfo-instance="<?php echo esc_attr( $wfo_instance ); ?>"
	data-wfo-layout="<?php echo esc_attr( $wfo_layout ); ?>"
	<?php echo '' !== $wfo_dark ? 'data-wfo-dark="' . esc_attr( $wfo_dark ) . '"' : ''; ?>
	style="<?php echo esc_attr( $wfo_root_style ); ?>">

	<script type="application/json" class="wfo-data"><?php echo \WFO\Render::json( $wfo_json_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_* flags escape the payload for inline use. ?></script>

	<?php if ( ! $wfo_is_open ) : ?>
		<?php echo \WFO\Render::template( 'closed', $data, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output is escaped internally. ?>
	<?php endif; ?>

	<?php if ( $wfo_show_search ) : ?>
		<div class="wfo-search" role="search">
			<label class="wfo-visually-hidden" for="<?php echo esc_attr( $wfo_search_id ); ?>"><?php echo esc_html( isset( $wfo_strings['search'] ) ? $wfo_strings['search'] : __( 'Search', 'whatsapp-menu-orders' ) ); ?></label>
			<div class="wfo-search__box">
				<svg class="wfo-search__icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true" focusable="false">
					<circle cx="11" cy="11" r="6.5"></circle>
					<path d="m16 16 4 4"></path>
				</svg>
				<input id="<?php echo esc_attr( $wfo_search_id ); ?>"
					class="wfo-search__input"
					type="search"
					data-wfo-search
					placeholder="<?php echo esc_attr( isset( $wfo_strings['searchPlaceholder'] ) ? $wfo_strings['searchPlaceholder'] : __( 'Search the menu', 'whatsapp-menu-orders' ) ); ?>"
					autocomplete="off"
					spellcheck="false"
					aria-controls="<?php echo esc_attr( 'wfo-menu-' . $wfo_instance ); ?>">
				<button class="wfo-search__clear" type="button" data-wfo-search-clear aria-label="<?php echo esc_attr( isset( $wfo_strings['close'] ) ? $wfo_strings['close'] : __( 'Close', 'whatsapp-menu-orders' ) ); ?>" hidden>
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
						<path d="M6 6l12 12M18 6L6 18"></path>
					</svg>
				</button>
			</div>
			<p class="wfo-search__status wfo-visually-hidden" data-wfo-search-status aria-live="polite"></p>
		</div>
	<?php endif; ?>

	<?php if ( $wfo_show_catbar ) : ?>
		<nav class="wfo-catbar" data-wfo-catbar aria-label="<?php echo esc_attr__( 'Menu categories', 'whatsapp-menu-orders' ); ?>">
			<div class="wfo-catbar__track">
				<?php foreach ( $wfo_sections as $wfo_index => $wfo_section ) : ?>
					<a class="wfo-catbar__link<?php echo 0 === $wfo_index ? ' is-active' : ''; ?>"
						data-wfo-cat="<?php echo esc_attr( $wfo_section['slug'] ); ?>"
						href="<?php echo esc_url( '#wfo-cat-' . $wfo_section['slug'] ); ?>"
						<?php echo 0 === $wfo_index ? 'aria-current="true"' : ''; ?>><?php echo esc_html( $wfo_section['name'] ); ?></a>
				<?php endforeach; ?>
			</div>
		</nav>
	<?php endif; ?>

	<div class="wfo-menu" id="<?php echo esc_attr( 'wfo-menu-' . $wfo_instance ); ?>">
		<?php foreach ( $wfo_sections as $wfo_section ) : ?>
			<section class="wfo-cat" id="<?php echo esc_attr( 'wfo-cat-' . $wfo_section['slug'] ); ?>" data-wfo-section="<?php echo esc_attr( $wfo_section['slug'] ); ?>">
				<header class="wfo-cat__head">
					<h2 class="wfo-cat__title"><?php echo esc_html( $wfo_section['name'] ); ?></h2>
				</header>
				<?php if ( '' !== trim( (string) $wfo_section['description'] ) ) : ?>
					<p class="wfo-cat__desc"><?php echo esc_html( $wfo_section['description'] ); ?></p>
				<?php endif; ?>
				<div class="wfo-grid">
					<?php
					foreach ( $wfo_section['items'] as $wfo_item ) {
						$wfo_card_args         = $args;
						$wfo_card_args['item'] = $wfo_item;
						echo \WFO\Render::template( 'item-card', $data, $wfo_card_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output is escaped internally.
					}
					?>
				</div>
			</section>
		<?php endforeach; ?>

		<?php if ( ! $wfo_sections ) : ?>
			<p class="wfo-empty__title"><?php echo esc_html__( 'The menu is being prepared. Please check back soon.', 'whatsapp-menu-orders' ); ?></p>
		<?php endif; ?>
	</div>

	<p class="wfo-noresults" data-wfo-no-results hidden><?php echo esc_html( isset( $wfo_strings['noResults'] ) ? $wfo_strings['noResults'] : __( 'Nothing matches that search.', 'whatsapp-menu-orders' ) ); ?></p>

	<button class="wfo-cartbtn<?php echo $wfo_sticky_cart ? '' : ' wfo-cartbtn--static'; ?>"
		type="button"
		data-wfo-open-cart
		aria-controls="<?php echo esc_attr( $wfo_drawer_id ); ?>"
		aria-expanded="false"
		aria-describedby="<?php echo esc_attr( $wfo_total_id ); ?>">
		<span class="wfo-cartbtn__count" data-wfo-cart-count>0</span>
		<span class="wfo-cartbtn__label"><?php echo esc_html( isset( $wfo_strings['viewOrder'] ) ? $wfo_strings['viewOrder'] : __( 'View order', 'whatsapp-menu-orders' ) ); ?></span>
		<span class="wfo-cartbtn__total" id="<?php echo esc_attr( $wfo_total_id ); ?>" data-wfo-cart-total></span>
	</button>
	<span class="wfo-visually-hidden" data-wfo-cart-status aria-live="polite"></span>

	<?php echo \WFO\Render::template( 'dialog', $data, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output is escaped internally. ?>
	<?php echo \WFO\Render::template( 'cart-drawer', $data, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output is escaped internally. ?>
	<?php echo \WFO\Render::template( 'confirmation', $data, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output is escaped internally. ?>

	<noscript>
		<p class="wfo-noscript">
			<?php
			echo esc_html__(
				'This menu is interactive: enable JavaScript to choose extras and send your order. You can also message us directly on WhatsApp.',
				'whatsapp-menu-orders'
			);
			?>
			<?php if ( ! empty( $wfo_settings['whatsappNumber'] ) ) : ?>
				<a href="<?php echo esc_url( 'https://wa.me/' . preg_replace( '/\D+/', '', (string) $wfo_settings['whatsappNumber'] ) ); ?>" rel="noopener" target="_blank">WhatsApp</a>
			<?php endif; ?>
		</p>
	</noscript>
</div>
<?php
/* The dialog lives outside the menu flow but inside the root; the id above is unique per instance. */
?>
