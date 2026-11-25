<?php
/**
 * WhatsApp number normalization and click-to-chat links.
 *
 * The server never talks to WhatsApp: it only builds wa.me URLs that the
 * browser opens. This keeps the plugin free of API keys and sessions.
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and normalizes WhatsApp click-to-chat links.
 */
class WhatsApp {

	/**
	 * Shortest acceptable international number, in digits.
	 */
	const MIN_DIGITS = 8;

	/**
	 * Longest acceptable international number, in digits.
	 */
	const MAX_DIGITS = 15;

	/**
	 * Normalize a phone number to the digits-only international form wa.me wants.
	 *
	 * Handles spaces, dashes, parentheses, a leading "+", the international
	 * "00" prefix and a national trunk "0" that has to be replaced by the
	 * configured country code. Already-international numbers pass through
	 * unchanged, so the method is idempotent.
	 *
	 * @param string $number       Raw number, in any human format.
	 * @param string $country_code Calling code without "+", e.g. "52".
	 * @return string Digits only, or '' when the number cannot be normalized.
	 */
	public static function normalize( $number, $country_code = '' ) {
		$raw = trim( (string) $number );

		if ( '' === $raw ) {
			return '';
		}

		$digits = preg_replace( '/\D+/', '', $raw );
		$digits = is_string( $digits ) ? $digits : '';

		if ( '' === $digits ) {
			return '';
		}

		// International prefix written as "00" (e.g. 0044 7700 900123).
		if ( 0 === strpos( $digits, '00' ) ) {
			$digits = substr( $digits, 2 );
		}

		// National trunk prefix: replace the leading 0 with the country code.
		if ( '' !== $digits && '0' === $digits[0] ) {
			$code = preg_replace( '/\D+/', '', (string) $country_code );
			$code = is_string( $code ) ? ltrim( $code, '0' ) : '';

			if ( '' === $code ) {
				return '';
			}

			$digits = $code . substr( $digits, 1 );
		}

		// Reject empty and all-zero garbage before the length check.
		if ( '' === $digits || '' === ltrim( $digits, '0' ) ) {
			return '';
		}

		$length = strlen( $digits );

		if ( $length < self::MIN_DIGITS || $length > self::MAX_DIGITS ) {
			return '';
		}

		return $digits;
	}

	/**
	 * Build a wa.me link.
	 *
	 * @param string $message Text to prefill in WhatsApp.
	 * @param string $number  Optional number; the configured one is used when empty.
	 * @return string The URL, or '' when no valid number is available.
	 */
	public static function link( $message, $number = '' ) {
		$settings = Settings::all();
		$candidate = trim( (string) $number );

		if ( '' === $candidate ) {
			$candidate = isset( $settings['whatsapp_number'] ) ? (string) $settings['whatsapp_number'] : '';
		}

		$digits = self::normalize(
			$candidate,
			isset( $settings['country_code'] ) ? (string) $settings['country_code'] : ''
		);

		if ( '' === $digits ) {
			return '';
		}

		return 'https://wa.me/' . $digits . '?text=' . rawurlencode( (string) $message );
	}

	/**
	 * Link to the configured restaurant number, used by the "resend" admin action.
	 *
	 * @param string $message Text to prefill in WhatsApp.
	 * @return string The URL, or '' when the configured number is missing/invalid.
	 */
	public static function message_permalink( $message ) {
		$settings = Settings::all();
		$number   = isset( $settings['whatsapp_number'] ) ? (string) $settings['whatsapp_number'] : '';

		return self::link( (string) $message, $number );
	}
}
