<?php
/**
 * Front-end asset registration.
 *
 * @package WFO
 */

namespace WFO;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and enqueues the front-end CSS/JS and the design tokens.
 */
class Assets {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
	}

	/**
	 * Register handles so Elementor can declare them as dependencies.
	 */
	public static function register() {
		wp_register_style( 'wfo-front', WFO_URL . 'assets/css/wfo.css', array(), WFO_VERSION );
		wp_register_script( 'wfo-front', WFO_URL . 'assets/js/wfo.js', array(), WFO_VERSION, true );

		/*
		 * Attach the design tokens while registering, not while rendering.
		 * Elementor prints the stylesheet in wp_head (it collects widget style
		 * dependencies early), so a wp_add_inline_style() call made later, from
		 * the widget render, would be silently dropped.
		 */
		$css = self::tokens_css();
		$custom = (string) Settings::get( 'custom_css' );

		if ( '' !== trim( $custom ) ) {
			$css .= "\n/* Custom CSS from settings */\n" . $custom . "\n";
		}

		if ( '' !== trim( $css ) ) {
			wp_add_inline_style( 'wfo-front', $css );
		}
	}

	/**
	 * Enqueue and attach the settings driven CSS.
	 */
	public static function enqueue() {
		if ( ! wp_style_is( 'wfo-front', 'registered' ) ) {
			self::register();
		}

		wp_enqueue_style( 'wfo-front' );
		wp_enqueue_script( 'wfo-front' );
	}

	/**
	 * CSS variables derived from the design settings.
	 *
	 * @return string
	 */
	public static function tokens_css() {
		$accent = Settings::get( 'accent' );
		$accent = $accent ? $accent : '#e2572b';
		$radius = (int) Settings::get( 'radius' );
		$dark   = Settings::get( 'dark_mode' );

		$css = ':root{--wfo-accent:' . $accent . ';--wfo-radius:' . $radius . 'px;--wfo-radius-lg:' . ( $radius + 8 ) . 'px;}';

		if ( 'dark' === $dark ) {
			$css .= '.wfo{--wfo-bg:#15120f;--wfo-surface:#1e1a16;--wfo-ink:#f4efe9;--wfo-muted:#a79e95;--wfo-line:#332d27;}';
		}

		if ( 'light' === $dark ) {
			$css .= '.wfo{color-scheme:light;}';
		}

		return $css;
	}
}
