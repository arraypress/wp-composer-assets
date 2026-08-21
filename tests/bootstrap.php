<?php
/**
 * Test bootstrap.
 *
 * Stubs the handful of WordPress functions the loader touches so asset
 * resolution can be tested against a real directory tree, with no WordPress
 * install.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/wp-composer-assets-tests/wp/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( string $path ): string {
		$path = str_replace( '\\', '/', $path );

		return preg_replace( '|(?<=.)/+|', '/', $path );
	}
}

if ( ! function_exists( 'content_url' ) ) {
	function content_url( string $path = '' ): string {
		return 'https://example.test/wp-content' . $path;
	}
}

if ( ! function_exists( 'site_url' ) ) {
	function site_url( string $path = '' ): string {
		return 'https://example.test' . $path;
	}
}

require_once __DIR__ . '/../vendor/autoload.php';
