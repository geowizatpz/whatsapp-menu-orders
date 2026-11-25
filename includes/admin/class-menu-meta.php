<?php
/**
 * Menu item meta box, save handler and the item list columns.
 *
 * @package WFO
 */

namespace WFO\Admin;

use WFO\Money;
use WFO\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Everything the owner edits on a single menu item.
 */
class Menu_Meta {

	/**
	 * Save nonce action.
	 *
	 * @var string
	 */
	const NONCE = 'wfo_item_meta';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . WFO_CPT_ITEM, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_' . WFO_CPT_ITEM . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . WFO_CPT_ITEM . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . WFO_CPT_ITEM . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_filter( 'edit_posts_per_page', array( __CLASS__, 'per_page' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'order_items' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'reorder_notice' ) );
		add_action( 'wp_ajax_wfo_reorder', array( __CLASS__, 'ajax_reorder' ) );
		add_action( 'wp_ajax_wfo_reorder_items', array( __CLASS__, 'ajax_reorder' ) );
	}

	/**
	 * Register the meta box.
	 */
	public static function meta_boxes() {
		add_meta_box(
			'wfo_item_details',
			__( 'Menu item details', 'whatsapp-menu-orders' ),
			array( __CLASS__, 'render' ),
			WFO_CPT_ITEM,
			'normal',
			'high'
		);
	}

	/**
	 * Badge tones.
	 *
	 * @return array<string,string>
	 */
	public static function tones() {
		return array(
			'default' => __( 'Default', 'whatsapp-menu-orders' ),
			'hot'     => __( 'Hot / spicy', 'whatsapp-menu-orders' ),
			'new'     => __( 'New', 'whatsapp-menu-orders' ),
			'veg'     => __( 'Vegetarian', 'whatsapp-menu-orders' ),
		);
	}

	/**
	 * Is the item currently available?
	 *
	 * The meta row is stored as "1" (available) or "" (unavailable). A missing row
	 * means the item was never toggled and is available, which is also how the menu
	 * query treats it.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	private static function is_available( $post_id ) {
		$post_id = absint( $post_id );
		$value   = get_post_meta( $post_id, '_wfo_available', true );

		if ( '' === (string) $value && ! metadata_exists( 'post', $post_id, '_wfo_available' ) ) {
			return true;
		}

		return '1' === (string) $value;
	}

	/**
	 * Currency symbol for the price inputs.
	 *
	 * @return string
	 */
	private static function symbol() {
		return Money::symbol( (string) Settings::get( 'currency' ) );
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public static function render( $post ) {
		$price      = (int) get_post_meta( $post->ID, '_wfo_price', true );
		$sale       = (int) get_post_meta( $post->ID, '_wfo_sale_price', true );
		$available  = self::is_available( $post->ID );
		$name_es    = (string) get_post_meta( $post->ID, '_wfo_name_es', true );
		$desc_es    = (string) get_post_meta( $post->ID, '_wfo_desc_es', true );
		$badges     = self::badges_for_form( $post->ID );
		$groups     = self::groups_for_form( get_post_meta( $post->ID, '_wfo_groups', true ) );
		$presets    = self::presets();
		$symbol     = self::symbol();
		$settings_url = admin_url( 'admin.php?page=wfo-settings&tab=extras' );

		wp_nonce_field( self::NONCE, 'wfo_item_meta_nonce' );
		?>
		<div class="wfo-meta">
			<div class="wfo-meta__row wfo-meta__row--2">
				<p class="wfo-field">
					<label for="wfo-price"><?php esc_html_e( 'Price', 'whatsapp-menu-orders' ); ?></label>
					<span class="wfo-money-wrap">
						<span class="wfo-money-prefix"><?php echo esc_html( $symbol ); ?></span>
						<input type="text" id="wfo-price" class="wfo-money-input" name="wfo_price" value="<?php echo esc_attr( Money::to_input( $price ) ); ?>" inputmode="decimal" autocomplete="off" placeholder="0.00">
					</span>
					<span class="description"><?php esc_html_e( 'Per unit. When a sale price is lower, the menu shows this price crossed out.', 'whatsapp-menu-orders' ); ?></span>
				</p>

				<p class="wfo-field">
					<label for="wfo-sale-price"><?php esc_html_e( 'Sale price', 'whatsapp-menu-orders' ); ?></label>
					<span class="wfo-money-wrap">
						<span class="wfo-money-prefix"><?php echo esc_html( $symbol ); ?></span>
						<input type="text" id="wfo-sale-price" class="wfo-money-input" name="wfo_sale_price" value="<?php echo esc_attr( Money::to_input( $sale ) ); ?>" inputmode="decimal" autocomplete="off" placeholder="0.00">
					</span>
					<span class="description"><?php esc_html_e( '0 = no sale. When lower than the price the menu shows the regular price crossed out.', 'whatsapp-menu-orders' ); ?></span>
				</p>
			</div>

			<p class="wfo-field">
				<label class="wfo-switch" for="wfo-available">
					<input type="checkbox" class="wfo-switch__input" id="wfo-available" name="wfo_available" value="1" <?php checked( $available ); ?>>
					<span class="wfo-switch__track" aria-hidden="true"></span>
					<span class="wfo-switch__label"><?php esc_html_e( 'Available on the menu', 'whatsapp-menu-orders' ); ?></span>
				</label>
				<span class="description"><?php esc_html_e( 'Turn off to hide a sold out item without deleting it.', 'whatsapp-menu-orders' ); ?></span>
			</p>

			<div class="wfo-field wfo-field--block">
				<span class="wfo-field__label"><?php esc_html_e( 'Badges', 'whatsapp-menu-orders' ); ?></span>
				<p class="description"><?php esc_html_e( 'Small labels shown on the menu card, for example "Spicy" or "New".', 'whatsapp-menu-orders' ); ?></p>

				<div class="wfo-badges" data-wfo-badges>
					<?php foreach ( $badges as $index => $badge ) : ?>
						<?php self::badge_row( $index, $badge ); ?>
					<?php endforeach; ?>
				</div>

				<p>
					<button type="button" class="button" data-wfo-add-badge><?php esc_html_e( '+ Add badge', 'whatsapp-menu-orders' ); ?></button>
				</p>

				<template data-wfo-tpl-badge>
					<?php self::badge_row( '__b__', array( 'label' => '', 'tone' => 'default' ) ); ?>
				</template>
			</div>

			<div class="wfo-meta__row wfo-meta__row--2">
				<p class="wfo-field">
					<label for="wfo-name-es"><?php esc_html_e( 'Spanish name', 'whatsapp-menu-orders' ); ?></label>
					<input type="text" id="wfo-name-es" class="regular-text" name="wfo_name_es" value="<?php echo esc_attr( $name_es ); ?>">
					<span class="description"><?php esc_html_e( 'Optional. Used when the menu language is Spanish.', 'whatsapp-menu-orders' ); ?></span>
				</p>

				<p class="wfo-field">
					<label for="wfo-desc-es"><?php esc_html_e( 'Spanish description', 'whatsapp-menu-orders' ); ?></label>
					<textarea id="wfo-desc-es" class="widefat" name="wfo_desc_es" rows="3"><?php echo esc_textarea( $desc_es ); ?></textarea>
					<span class="description"><?php esc_html_e( 'Optional. Replaces the description when the menu language is Spanish.', 'whatsapp-menu-orders' ); ?></span>
				</p>
			</div>

			<hr class="wfo-meta__rule">

			<div class="wfo-field wfo-field--block">
				<span class="wfo-field__label"><?php esc_html_e( 'Extras and option groups', 'whatsapp-menu-orders' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Add groups such as Size, Extras or Drinks. Each group can be a single choice (radio) or several choices (checkboxes). Prices are added to the item price and may be negative.', 'whatsapp-menu-orders' ); ?>
					<br>
					<?php esc_html_e( 'Min and max limit how many options a customer may pick. Max 0 means no limit.', 'whatsapp-menu-orders' ); ?>
				</p>

				<?php if ( $presets ) : ?>
					<p class="wfo-preset-picker">
						<label for="wfo-preset"><?php esc_html_e( 'Insert preset', 'whatsapp-menu-orders' ); ?></label>
						<select id="wfo-preset" data-wfo-preset>
							<option value=""><?php esc_html_e( 'Choose a saved preset…', 'whatsapp-menu-orders' ); ?></option>
							<?php foreach ( $presets as $preset_index => $preset ) : ?>
								<option value="<?php echo esc_attr( (string) $preset_index ); ?>"><?php echo esc_html( $preset['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<a class="wfo-hint" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Manage presets', 'whatsapp-menu-orders' ); ?></a>
						<span class="description"><?php esc_html_e( 'Adds a copy of the preset to this item.', 'whatsapp-menu-orders' ); ?></span>
					</p>
				<?php else : ?>
					<p class="description">
						<?php
						/* translators: %s: settings screen URL. */
						$preset_note = __( 'No saved presets yet. Create reusable groups under <a href="%s">Settings → Extras</a>.', 'whatsapp-menu-orders' );
						printf(
							wp_kses_post( $preset_note ),
							esc_url( $settings_url )
						);
						?>
					</p>
				<?php endif; ?>

				<?php self::render_groups_builder( 'wfo_groups', $groups ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * One badge row.
	 *
	 * @param int|string $index Row index, or a template placeholder.
	 * @param array      $badge Badge values.
	 */
	private static function badge_row( $index, $badge ) {
		$base = 'wfo_badges[' . $index . ']';
		?>
		<div class="wfo-badge-row" data-wfo-badge>
			<input type="text" class="wfo-badge-row__label" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( isset( $badge['label'] ) ? $badge['label'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Badge text', 'whatsapp-menu-orders' ); ?>">
			<select name="<?php echo esc_attr( $base ); ?>[tone]">
				<?php foreach ( self::tones() as $tone => $label ) : ?>
					<option value="<?php echo esc_attr( $tone ); ?>" <?php selected( isset( $badge['tone'] ) ? $badge['tone'] : 'default', $tone ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button-link wfo-remove" data-wfo-remove-badge><?php esc_html_e( 'Remove', 'whatsapp-menu-orders' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Render the repeatable option group builder.
	 *
	 * Used by the menu item meta box and by the Extras tab of the settings page.
	 *
	 * @param string $name   Field base name, e.g. wfo_groups.
	 * @param array  $groups Groups in form shape.
	 */
	public static function render_groups_builder( $name, $groups = array() ) {
		$groups = self::groups_for_form( $groups );
		?>
		<div class="wfo-builder" data-wfo-builder data-wfo-name="<?php echo esc_attr( $name ); ?>">
			<div class="wfo-groups" data-wfo-groups>
				<?php if ( empty( $groups ) ) : ?>
					<p class="wfo-empty" data-wfo-empty><?php esc_html_e( 'No option groups yet.', 'whatsapp-menu-orders' ); ?></p>
				<?php else : ?>
					<?php foreach ( $groups as $index => $group ) : ?>
						<?php self::group_card( $name, $index, $group ); ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<p class="wfo-builder__actions">
				<button type="button" class="button button-secondary" data-wfo-add-group><?php esc_html_e( '+ Add option group', 'whatsapp-menu-orders' ); ?></button>
			</p>

			<template data-wfo-tpl-group>
				<?php self::group_card( $name, '__g__', self::blank_group() ); ?>
			</template>

			<template data-wfo-tpl-option>
				<?php self::option_row( $name . '[__g__]', '__o__', self::blank_option() ); ?>
			</template>
		</div>
		<?php
	}

	/**
	 * One option group card.
	 *
	 * @param string     $name  Field base name.
	 * @param int|string $index Group index, or a template placeholder.
	 * @param array      $group Group in form shape.
	 */
	private static function group_card( $name, $index, $group ) {
		$base    = $name . '[' . $index . ']';
		$symbol  = self::symbol();
		$options = isset( $group['options'] ) && is_array( $group['options'] ) ? $group['options'] : array();
		?>
		<div class="wfo-group" data-wfo-group data-index="<?php echo esc_attr( (string) $index ); ?>">
			<div class="wfo-group__head">
				<span class="wfo-group__title"><?php esc_html_e( 'Option group', 'whatsapp-menu-orders' ); ?></span>
				<button type="button" class="button-link button-link-delete wfo-remove" data-wfo-remove-group><?php esc_html_e( 'Remove group', 'whatsapp-menu-orders' ); ?></button>
			</div>

			<div class="wfo-group__grid">
				<label class="wfo-field wfo-field--wide">
					<span class="wfo-field__label"><?php esc_html_e( 'Group label', 'whatsapp-menu-orders' ); ?></span>
					<input type="text" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( isset( $group['label'] ) ? $group['label'] : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Choose a size', 'whatsapp-menu-orders' ); ?>">
				</label>

				<label class="wfo-field">
					<span class="wfo-field__label"><?php esc_html_e( 'Selection', 'whatsapp-menu-orders' ); ?></span>
					<select name="<?php echo esc_attr( $base ); ?>[type]" data-wfo-type>
						<option value="checkbox" <?php selected( isset( $group['type'] ) ? $group['type'] : 'checkbox', 'checkbox' ); ?>><?php esc_html_e( 'Several choices (checkboxes)', 'whatsapp-menu-orders' ); ?></option>
						<option value="radio" <?php selected( isset( $group['type'] ) ? $group['type'] : 'checkbox', 'radio' ); ?>><?php esc_html_e( 'One choice (radio)', 'whatsapp-menu-orders' ); ?></option>
					</select>
				</label>

				<label class="wfo-check wfo-check--required">
					<input type="checkbox" name="<?php echo esc_attr( $base ); ?>[required]" value="1" <?php checked( ! empty( $group['required'] ) ); ?>>
					<?php esc_html_e( 'Required', 'whatsapp-menu-orders' ); ?>
				</label>

				<label class="wfo-field wfo-field--tiny">
					<span class="wfo-field__label"><?php esc_html_e( 'Min', 'whatsapp-menu-orders' ); ?></span>
					<input type="number" min="0" max="99" step="1" name="<?php echo esc_attr( $base ); ?>[min]" value="<?php echo esc_attr( (string) ( isset( $group['min'] ) ? (int) $group['min'] : 0 ) ); ?>" data-wfo-min>
				</label>

				<label class="wfo-field wfo-field--tiny">
					<span class="wfo-field__label"><?php esc_html_e( 'Max', 'whatsapp-menu-orders' ); ?></span>
					<input type="number" min="0" max="99" step="1" name="<?php echo esc_attr( $base ); ?>[max]" value="<?php echo esc_attr( (string) ( isset( $group['max'] ) ? (int) $group['max'] : 0 ) ); ?>" data-wfo-max>
					<span class="description"><?php esc_html_e( '0 = no limit', 'whatsapp-menu-orders' ); ?></span>
				</label>
			</div>

			<div class="wfo-options" data-wfo-options>
				<?php foreach ( $options as $option_index => $option ) : ?>
					<?php self::option_row( $base, $option_index, $option ); ?>
				<?php endforeach; ?>
			</div>

			<p class="wfo-group__actions">
				<button type="button" class="button" data-wfo-add-option><?php esc_html_e( '+ Add option', 'whatsapp-menu-orders' ); ?></button>
				<span class="wfo-hint">
					<?php
					printf(
						/* translators: %s: example amount with the currency symbol. */
						esc_html__( 'Prices are added to the item price (e.g. -%s).', 'whatsapp-menu-orders' ),
						esc_html( $symbol . '1.00' )
					);
					?>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * One option row inside a group.
	 *
	 * @param string     $group_base Group field base name, e.g. wfo_groups[0].
	 * @param int|string $index      Option index, or a template placeholder.
	 * @param array      $option     Option in form shape.
	 */
	private static function option_row( $group_base, $index, $option ) {
		$base   = $group_base . '[options][' . $index . ']';
		$symbol = self::symbol();
		?>
		<div class="wfo-option" data-wfo-option>
			<input type="text" class="wfo-option__label" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( isset( $option['label'] ) ? $option['label'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Option label', 'whatsapp-menu-orders' ); ?>">

			<span class="wfo-money-wrap wfo-money-wrap--sm">
				<span class="wfo-money-prefix"><?php echo esc_html( $symbol ); ?></span>
				<input type="text" class="wfo-money-input wfo-option__price" name="<?php echo esc_attr( $base ); ?>[price]" value="<?php echo esc_attr( Money::to_input( isset( $option['price'] ) ? (int) $option['price'] : 0 ) ); ?>" inputmode="decimal" autocomplete="off" placeholder="0.00">
			</span>

			<label class="wfo-check">
				<input type="checkbox" name="<?php echo esc_attr( $base ); ?>[default]" value="1" <?php checked( ! empty( $option['default'] ) ); ?>>
				<?php esc_html_e( 'Default', 'whatsapp-menu-orders' ); ?>
			</label>

			<label class="wfo-check">
				<input type="checkbox" name="<?php echo esc_attr( $base ); ?>[soldout]" value="1" <?php checked( ! empty( $option['soldout'] ) ); ?>>
				<?php esc_html_e( 'Sold out', 'whatsapp-menu-orders' ); ?>
			</label>

			<button type="button" class="button-link button-link-delete wfo-remove" data-wfo-remove-option><?php esc_html_e( 'Remove', 'whatsapp-menu-orders' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Empty group in form shape.
	 *
	 * @return array
	 */
	private static function blank_group() {
		return array(
			'label'    => '',
			'type'     => 'checkbox',
			'required' => false,
			'min'      => 0,
			'max'      => 0,
			'options'  => array(),
		);
	}

	/**
	 * Empty option in form shape.
	 *
	 * @return array
	 */
	private static function blank_option() {
		return array(
			'label'   => '',
			'price'   => 0,
			'default' => false,
			'soldout' => false,
		);
	}

	/**
	 * Shape saved groups for the builder form.
	 *
	 * @param mixed $raw Stored groups.
	 * @return array
	 */
	private static function groups_for_form( $raw ) {
		$out = array();

		if ( ! is_array( $raw ) ) {
			return $out;
		}

		foreach ( $raw as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$options     = array();
			$raw_options = isset( $group['options'] ) && is_array( $group['options'] ) ? $group['options'] : array();

			foreach ( $raw_options as $option ) {
				if ( ! is_array( $option ) ) {
					continue;
				}

				$options[] = array(
					'label'   => isset( $option['label'] ) ? (string) $option['label'] : '',
					'price'   => isset( $option['price'] ) ? (int) $option['price'] : 0,
					'default' => ! empty( $option['default'] ),
					'soldout' => ! empty( $option['soldout'] ),
				);
			}

			$out[] = array(
				'label'    => isset( $group['label'] ) ? (string) $group['label'] : '',
				'type'     => ( isset( $group['type'] ) && 'radio' === $group['type'] ) ? 'radio' : 'checkbox',
				'required' => ! empty( $group['required'] ),
				'min'      => isset( $group['min'] ) ? max( 0, (int) $group['min'] ) : 0,
				'max'      => isset( $group['max'] ) ? max( 0, (int) $group['max'] ) : 0,
				'options'  => $options,
			);
		}

		return $out;
	}

	/**
	 * Saved badges in form shape.
	 *
	 * @param int $post_id Post id.
	 * @return array
	 */
	private static function badges_for_form( $post_id ) {
		$raw = get_post_meta( $post_id, '_wfo_badges', true );

		if ( is_string( $raw ) ) {
			$raw = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$tones = array_keys( self::tones() );
		$out   = array();

		foreach ( $raw as $badge ) {
			if ( is_array( $badge ) ) {
				if ( isset( $badge['label'] ) ) {
					$label = (string) $badge['label'];
					$tone  = isset( $badge['tone'] ) ? sanitize_key( $badge['tone'] ) : 'default';
				} else {
					// Older seeds stored badges as [ label, tone ] pairs.
					$values = array_values( $badge );
					$label  = isset( $values[0] ) && is_scalar( $values[0] ) ? (string) $values[0] : '';
					$tone   = isset( $values[1] ) && is_scalar( $values[1] ) ? sanitize_key( (string) $values[1] ) : 'default';
				}
			} else {
				$label = (string) $badge;
				$tone  = 'default';
			}

			if ( '' === trim( $label ) ) {
				continue;
			}

			$out[] = array(
				'label' => $label,
				'tone'  => in_array( $tone, $tones, true ) ? $tone : 'default',
			);
		}

		return $out;
	}

	/**
	 * Sanitize and normalize option groups.
	 *
	 * Shared by the menu item meta box and the extras presets on the settings page.
	 * Group ids become g1, g2… and option ids o1, o2… per group.
	 *
	 * @param mixed $groups    Raw groups, typically from $_POST.
	 * @param bool  $with_ids  Include normalized ids (false for saved presets).
	 * @param bool  $from_form Prices are form values in major units; when false they
	 *                         are already stored minor units (reading a preset).
	 * @return array
	 */
	public static function sanitize_groups( $groups, $with_ids = true, $from_form = true ) {
		$out = array();

		if ( ! is_array( $groups ) ) {
			return $out;
		}

		$group_number = 0;

		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$label = isset( $group['label'] ) ? sanitize_text_field( (string) $group['label'] ) : '';

			if ( '' === $label ) {
				continue;
			}

			$type     = ( isset( $group['type'] ) && 'radio' === $group['type'] ) ? 'radio' : 'checkbox';
			$required = ! empty( $group['required'] );
			$min      = isset( $group['min'] ) ? max( 0, min( 99, (int) $group['min'] ) ) : 0;
			$max      = isset( $group['max'] ) ? max( 0, min( 99, (int) $group['max'] ) ) : 0;
			$options  = array();
			$o_number = 0;

			$raw_options = isset( $group['options'] ) && is_array( $group['options'] ) ? $group['options'] : array();

			foreach ( $raw_options as $option ) {
				if ( ! is_array( $option ) ) {
					continue;
				}

				$option_label = isset( $option['label'] ) ? sanitize_text_field( (string) $option['label'] ) : '';

				if ( '' === $option_label ) {
					continue;
				}

				$price   = isset( $option['price'] ) ? ( $from_form ? Money::to_minor( $option['price'] ) : (int) $option['price'] ) : 0;
				$soldout = ! empty( $option['soldout'] );
				$default = ! empty( $option['default'] ) && ! $soldout;

				++$o_number;

				$clean_option = array(
					'label'   => $option_label,
					'price'   => $price,
					'default' => $default,
					'soldout' => $soldout,
				);

				if ( $with_ids ) {
					$clean_option = array( 'id' => 'o' . $o_number ) + $clean_option;
				}

				$options[] = $clean_option;
			}

			if ( ! $options ) {
				continue;
			}

			if ( 'radio' === $type ) {
				$min = $required ? 1 : 0;
				$max = 1;
			} elseif ( $max > 0 && $min > $max ) {
				$min = $max;
			}

			++$group_number;

			$clean_group = array(
				'label'    => $label,
				'type'     => $type,
				'required' => $required,
				'min'      => $min,
				'max'      => $max,
				'options'  => $options,
			);

			if ( $with_ids ) {
				$clean_group = array( 'id' => 'g' . $group_number ) + $clean_group;
			}

			$out[] = $clean_group;
		}

		return $out;
	}

	/**
	 * Saved extra presets, normalized.
	 *
	 * @return array
	 */
	public static function presets() {
		$raw = get_option( 'wfo_extra_presets', array() );

		return self::sanitize_groups( is_array( $raw ) ? $raw : array(), false, false );
	}

	/**
	 * Presets shaped for the admin JS.
	 *
	 * @return array
	 */
	private static function presets_for_js() {
		$out = array();

		foreach ( self::presets() as $group ) {
			$options = array();

			foreach ( $group['options'] as $option ) {
				$options[] = array(
					'label'   => $option['label'],
					'price'   => Money::to_input( $option['price'] ),
					'default' => (bool) $option['default'],
					'soldout' => (bool) $option['soldout'],
				);
			}

			$out[] = array(
				'label'    => $group['label'],
				'type'     => $group['type'],
				'required' => (bool) $group['required'],
				'min'      => (int) $group['min'],
				'max'      => (int) $group['max'],
				'options'  => $options,
			);
		}

		return $out;
	}

	/**
	 * Persist the meta box values.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post object.
	 */
	public static function save( $post_id, $post ) {
		if ( ! $post instanceof \WP_Post || WFO_CPT_ITEM !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['wfo_item_meta_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['wfo_item_meta_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$price = isset( $_POST['wfo_price'] ) ? sanitize_text_field( wp_unslash( $_POST['wfo_price'] ) ) : '';
		$sale  = isset( $_POST['wfo_sale_price'] ) ? sanitize_text_field( wp_unslash( $_POST['wfo_sale_price'] ) ) : '';

		update_post_meta( $post_id, '_wfo_price', max( 0, Money::to_minor( $price ) ) );
		update_post_meta( $post_id, '_wfo_sale_price', max( 0, Money::to_minor( $sale ) ) );
		update_post_meta( $post_id, '_wfo_available', isset( $_POST['wfo_available'] ) ? '1' : '' );

		$badges = array();

		if ( isset( $_POST['wfo_badges'] ) && is_array( $_POST['wfo_badges'] ) ) {
			$tones = array_keys( self::tones() );

			foreach ( wp_unslash( $_POST['wfo_badges'] ) as $badge ) {
				if ( ! is_array( $badge ) ) {
					continue;
				}

				$label = isset( $badge['label'] ) ? sanitize_text_field( (string) $badge['label'] ) : '';

				if ( '' === $label ) {
					continue;
				}

				$tone = isset( $badge['tone'] ) ? sanitize_key( $badge['tone'] ) : 'default';

				$badges[] = array(
					'label' => $label,
					'tone'  => in_array( $tone, $tones, true ) ? $tone : 'default',
				);
			}
		}

		update_post_meta( $post_id, '_wfo_badges', $badges );

		$name_es = isset( $_POST['wfo_name_es'] ) ? sanitize_text_field( wp_unslash( $_POST['wfo_name_es'] ) ) : '';
		$desc_es = isset( $_POST['wfo_desc_es'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wfo_desc_es'] ) ) : '';

		update_post_meta( $post_id, '_wfo_name_es', $name_es );
		update_post_meta( $post_id, '_wfo_desc_es', $desc_es );

		$groups = isset( $_POST['wfo_groups'] ) ? self::sanitize_groups( wp_unslash( $_POST['wfo_groups'] ) ) : array();

		update_post_meta( $post_id, '_wfo_groups', $groups );
	}

	/**
	 * Item list columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$out = array();

		if ( isset( $columns['cb'] ) ) {
			$out['cb'] = $columns['cb'];
		}

		$out['title']          = isset( $columns['title'] ) ? $columns['title'] : __( 'Title', 'whatsapp-menu-orders' );
		$out['wfo_photo']      = __( 'Photo', 'whatsapp-menu-orders' );
		$out['wfo_price']      = __( 'Price', 'whatsapp-menu-orders' );
		$out['wfo_category']   = __( 'Category', 'whatsapp-menu-orders' );
		$out['wfo_available']  = __( 'Available', 'whatsapp-menu-orders' );
		$out['wfo_order']      = __( 'Order', 'whatsapp-menu-orders' );

		return $out;
	}

	/**
	 * Sortable item list columns.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable_columns( $columns ) {
		$columns['wfo_order'] = 'menu_order';

		return $columns;
	}

	/**
	 * Show every item on one list screen so drag ordering covers the whole menu.
	 *
	 * @param int    $per_page  Items per page.
	 * @param string $post_type Current post type.
	 * @return int
	 */
	public static function per_page( $per_page, $post_type ) {
		if ( WFO_CPT_ITEM === $post_type ) {
			return 200;
		}

		return $per_page;
	}

	/**
	 * Order the item list by the stored menu order by default.
	 *
	 * @param \WP_Query $query Query object.
	 */
	public static function order_items( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( WFO_CPT_ITEM !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( ! empty( $query->get( 'orderby' ) ) ) {
			return;
		}

		$query->set(
			'orderby',
			array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			)
		);
		$query->set( 'order', 'ASC' );
	}

	/**
	 * Item list column output.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post id.
	 */
	public static function column( $column, $post_id ) {
		switch ( $column ) {
			case 'wfo_photo':
				$thumb = get_the_post_thumbnail(
					$post_id,
					array( 56, 56 ),
					array(
						'class' => 'wfo-thumb__img',
						'alt'   => '',
					)
				);

				if ( $thumb ) {
					echo wp_kses_post( $thumb );
				} else {
					echo '<span class="wfo-thumb wfo-thumb--empty" aria-hidden="true"></span>';
				}
				break;

			case 'wfo_price':
				$regular = (int) get_post_meta( $post_id, '_wfo_price', true );
				$sale    = (int) get_post_meta( $post_id, '_wfo_sale_price', true );

				if ( $sale > 0 && $sale < $regular ) {
					printf(
						'<span class="wfo-price-now">%s</span> <s class="wfo-price-was">%s</s>',
						esc_html( Money::format( $sale ) ),
						esc_html( Money::format( $regular ) )
					);
				} else {
					printf( '<span class="wfo-price-now">%s</span>', esc_html( Money::format( $regular ) ) );
				}
				break;

			case 'wfo_category':
				$terms = get_the_terms( $post_id, WFO_TAX_CAT );

				if ( $terms && ! is_wp_error( $terms ) ) {
					$names = wp_list_pluck( $terms, 'name' );
					echo esc_html( implode( ', ', $names ) );
				} else {
					echo '<span class="wfo-muted">—</span>';
				}
				break;

			case 'wfo_available':
				$available = self::is_available( $post_id );
				printf(
					'<span class="wfo-availability %s"><span class="wfo-dot %s" aria-hidden="true"></span>%s</span>',
					$available ? 'is-available' : 'is-unavailable',
					$available ? 'wfo-dot--ok' : 'wfo-dot--off',
					esc_html( $available ? __( 'Available', 'whatsapp-menu-orders' ) : __( 'Unavailable', 'whatsapp-menu-orders' ) )
				);
				break;

			case 'wfo_order':
				$order = (int) get_post_field( 'menu_order', $post_id );
				printf(
					'<span class="wfo-drag" title="%1$s" aria-hidden="true">⋮⋮</span><span class="screen-reader-text">%1$s</span> <span class="wfo-order__num">%2$s</span>',
					esc_attr__( 'Drag to reorder', 'whatsapp-menu-orders' ),
					esc_html( (string) $order )
				);
				break;
		}
	}

	/**
	 * Enqueue the admin assets on item screens only.
	 */
	public static function assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || WFO_CPT_ITEM !== $screen->post_type ) {
			return;
		}

		if ( ! in_array( $screen->base, array( 'post', 'edit' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'wfo-admin', WFO_URL . 'assets/css/wfo-admin.css', array(), WFO_VERSION );
		wp_enqueue_script( 'wfo-admin', WFO_URL . 'assets/js/wfo-admin.js', array( 'jquery', 'jquery-ui-sortable' ), WFO_VERSION, true );

		wp_localize_script(
			'wfo-admin',
			'WFO_ADMIN',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'reorderNonce' => wp_create_nonce( 'wfo_reorder' ),
				'presets'      => self::presets_for_js(),
				'i18n'         => array(
					'noGroups'      => __( 'No option groups yet.', 'whatsapp-menu-orders' ),
					'reorderSaving' => __( 'Saving menu order…', 'whatsapp-menu-orders' ),
					'reorderSaved'  => __( 'Menu order saved.', 'whatsapp-menu-orders' ),
					'reorderError'  => __( 'The menu order could not be saved. Reload the page and try again.', 'whatsapp-menu-orders' ),
				),
			)
		);
	}

	/**
	 * Help text above the item list.
	 */
	public static function reorder_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'edit-' . WFO_CPT_ITEM !== $screen->id ) {
			return;
		}
		?>
		<div class="notice notice-info wfo-reorder-notice">
			<p>
				<?php esc_html_e( 'Drag rows by the handle in the Order column to set the order items appear in your menu.', 'whatsapp-menu-orders' ); ?>
				<span class="wfo-reorder-status" role="status" aria-live="polite"></span>
			</p>
		</div>
		<?php
	}

	/**
	 * AJAX handler for drag and drop ordering.
	 */
	public static function ajax_reorder() {
		if ( ! check_ajax_referer( 'wfo_reorder', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Reload the page and try again.', 'whatsapp-menu-orders' ) ), 403 );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to reorder menu items.', 'whatsapp-menu-orders' ) ), 403 );
		}

		$ids = array();

		if ( isset( $_POST['ids'] ) ) {
			$ids = (array) wp_unslash( $_POST['ids'] );
		} elseif ( isset( $_POST['order'] ) ) {
			$ids = (array) wp_unslash( $_POST['order'] );
		}

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( ! $ids ) {
			wp_send_json_error( array( 'message' => __( 'No menu items were sent.', 'whatsapp-menu-orders' ) ), 400 );
		}

		$position = 0;

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( ! $post || WFO_CPT_ITEM !== $post->post_type ) {
				continue;
			}

			if ( ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'         => $id,
					'menu_order' => $position,
				)
			);

			++$position;
		}

		wp_send_json_success(
			array(
				'count' => $position,
				'ids'   => $ids,
			)
		);
	}
}
