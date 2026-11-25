<?php
/**
 * Money formatting helpers. All amounts are integers in minor units.
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * Currency helpers.
 */
class Money {

	/**
	 * Supported currencies.
	 *
	 * @return array<string,array{label:string,symbol:string}>
	 */
	public static function currencies() {
		return array(
			'USD' => array(
				'label'  => __( 'US Dollar', 'whatsapp-menu-orders' ),
				'symbol' => '$',
			),
			'EUR' => array(
				'label'  => __( 'Euro', 'whatsapp-menu-orders' ),
				'symbol' => '€',
			),
			'MXN' => array(
				'label'  => __( 'Mexican Peso', 'whatsapp-menu-orders' ),
				'symbol' => '$',
			),
		);
	}

	/**
	 * Symbol for a currency code.
	 *
	 * @param string $code Currency code.
	 * @return string
	 */
	public static function symbol( $code ) {
		$currencies = self::currencies();

		return isset( $currencies[ $code ] ) ? $currencies[ $code ]['symbol'] : '$';
	}

	/**
	 * Format a minor-unit amount for display.
	 *
	 * @param int        $minor    Amount in minor units.
	 * @param array|null $settings Optional settings array.
	 * @return string
	 */
	public static function format( $minor, $settings = null ) {
		$settings = null === $settings ? Settings::all() : $settings;

		$decimals  = isset( $settings['decimals'] ) ? (int) $settings['decimals'] : 2;
		$thousands = isset( $settings['thousands_sep'] ) ? $settings['thousands_sep'] : ',';
		$decimal   = isset( $settings['decimal_sep'] ) ? $settings['decimal_sep'] : '.';
		$position  = isset( $settings['currency_position'] ) ? $settings['currency_position'] : 'left';
		$symbol    = self::symbol( isset( $settings['currency'] ) ? $settings['currency'] : 'USD' );

		// Storage is always cents; `decimals` only controls how many of them we print.
		$value  = (int) $minor / 100;
		$number = number_format( $value, $decimals, $decimal, $thousands );

		return 'right' === $position ? $number . $symbol : $symbol . $number;
	}

	/**
	 * Convert a human entered amount into minor units.
	 *
	 * Accepts "12.50", "12,50", "$12.50", "1,234.5", "" and null.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function to_minor( $value ) {
		if ( is_null( $value ) ) {
			return 0;
		}

		// Numeric input is read as major units: 185 means 185.00.
		$string = trim( (string) $value );

		if ( '' === $string ) {
			return 0;
		}

		$negative = 0 === strpos( $string, '-' );

		// Keep digits, separators and the minus sign only.
		$string = preg_replace( '/[^0-9,.\-]/u', '', $string );
		$string = ltrim( (string) $string, '-' );

		$last_comma = strrpos( $string, ',' );
		$last_dot   = strrpos( $string, '.' );

		// The right-most separator that has 1-2 trailing digits is the decimal mark.
		if ( false !== $last_comma && false !== $last_dot ) {
			if ( $last_comma > $last_dot ) {
				$string = str_replace( '.', '', $string );
				$string = str_replace( ',', '.', $string );
			} else {
				$string = str_replace( ',', '', $string );
			}
		} elseif ( false !== $last_comma ) {
			$after = strlen( $string ) - $last_comma - 1;
			if ( $after > 0 && $after <= 2 ) {
				$string = str_replace( ',', '.', $string );
			} else {
				$string = str_replace( ',', '', $string );
			}
		}

		$string = preg_replace( '/[^0-9.]/', '', $string );
		if ( '' === $string ) {
			return 0;
		}

		$amount = (float) $string;

		return (int) round( ( $negative ? -$amount : $amount ) * 100 );
	}

	/**
	 * Convert minor units back to a plain decimal string for admin inputs.
	 *
	 * @param int $minor Amount in minor units.
	 * @return string
	 */
	public static function to_input( $minor ) {
		return number_format( (int) $minor / 100, 2, '.', '' );
	}
}
