<?php
/**
 * Optional Spanish name and description for menu categories.
 *
 * Mirrors the per-item Spanish fields so a single menu can be shown in
 * English or Spanish without a multilingual plugin.
 *
 * @package WFO
 */

namespace WFO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Term meta fields on the menu category taxonomy.
 */
class Category_Meta {

	/**
	 * Meta key for the Spanish name.
	 */
	const NAME_ES = 'wfo_name_es';

	/**
	 * Meta key for the Spanish description.
	 */
	const DESC_ES = 'wfo_desc_es';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'wfo_category_add_form_fields', array( __CLASS__, 'add_fields' ) );
		add_action( 'wfo_category_edit_form_fields', array( __CLASS__, 'edit_fields' ) );
		add_action( 'created_wfo_category', array( __CLASS__, 'save' ) );
		add_action( 'edited_wfo_category', array( __CLASS__, 'save' ) );
	}

	/**
	 * Fields on the "add category" screen.
	 */
	public static function add_fields() {
		wp_nonce_field( 'wfo_category_meta', 'wfo_category_meta_nonce' );
		?>
		<div class="form-field">
			<label for="wfo_name_es"><?php esc_html_e( 'Spanish name', 'whatsapp-menu-orders' ); ?></label>
			<input type="text" name="wfo_name_es" id="wfo_name_es" value="" maxlength="120">
			<p><?php esc_html_e( 'Shown instead of the name above when the interface language is Spanish.', 'whatsapp-menu-orders' ); ?></p>
		</div>
		<div class="form-field">
			<label for="wfo_desc_es"><?php esc_html_e( 'Spanish description', 'whatsapp-menu-orders' ); ?></label>
			<textarea name="wfo_desc_es" id="wfo_desc_es" rows="3" maxlength="300"></textarea>
		</div>
		<?php
	}

	/**
	 * Fields on the "edit category" screen.
	 *
	 * @param \WP_Term $term Term being edited.
	 */
	public static function edit_fields( $term ) {
		$name_es = (string) get_term_meta( $term->term_id, self::NAME_ES, true );
		$desc_es = (string) get_term_meta( $term->term_id, self::DESC_ES, true );

		wp_nonce_field( 'wfo_category_meta', 'wfo_category_meta_nonce' );
		?>
		<tr class="form-field">
			<th scope="row"><label for="wfo_name_es"><?php esc_html_e( 'Spanish name', 'whatsapp-menu-orders' ); ?></label></th>
			<td>
				<input type="text" name="wfo_name_es" id="wfo_name_es" value="<?php echo esc_attr( $name_es ); ?>" maxlength="120">
				<p class="description"><?php esc_html_e( 'Shown instead of the name above when the interface language is Spanish.', 'whatsapp-menu-orders' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wfo_desc_es"><?php esc_html_e( 'Spanish description', 'whatsapp-menu-orders' ); ?></label></th>
			<td><textarea name="wfo_desc_es" id="wfo_desc_es" rows="3" maxlength="300"><?php echo esc_textarea( $desc_es ); ?></textarea></td>
		</tr>
		<?php
	}

	/**
	 * Persist the fields.
	 *
	 * @param int $term_id Term id.
	 */
	public static function save( $term_id ) {
		if ( ! isset( $_POST['wfo_category_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['wfo_category_meta_nonce'] ) ), 'wfo_category_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		$name_es = isset( $_POST['wfo_name_es'] ) ? sanitize_text_field( wp_unslash( $_POST['wfo_name_es'] ) ) : '';
		$desc_es = isset( $_POST['wfo_desc_es'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wfo_desc_es'] ) ) : '';

		update_term_meta( $term_id, self::NAME_ES, $name_es );
		update_term_meta( $term_id, self::DESC_ES, $desc_es );
	}
}
