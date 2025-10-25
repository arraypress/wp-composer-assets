<?php
/**
 * WordPress Composer Assets Helper Functions
 *
 * Global helper functions for registering and enqueuing assets from Composer packages.
 *
 * @package     ArrayPress\WP\ComposerAssets
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\WP\ComposerAssets\AssetLoader;

if ( ! function_exists( 'wp_enqueue_composer_script' ) ):
	/**
	 * Enqueue a JavaScript file from a Composer package
	 *
	 * Mirrors wp_enqueue_script() but resolves file paths relative to Composer packages.
	 * Automatically detects the assets directory and handles URL generation.
	 *
	 * @param string      $handle       Script handle for registration.
	 * @param string      $calling_file File path to resolve assets relative to. Use __FILE__.
	 * @param string      $file         Relative path to JS file from assets/ directory.
	 * @param array       $deps         Optional. Array of script handles this script depends on. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param bool        $in_footer    Optional. Whether to enqueue the script in the footer. Default true.
	 *
	 * @return bool True on success, false on failure.
	 */
	function wp_enqueue_composer_script(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		bool $in_footer = true
	): bool {
		return AssetLoader::enqueue_script( $handle, $calling_file, $file, $deps, $ver, $in_footer );
	}
endif;

if ( ! function_exists( 'wp_enqueue_composer_style' ) ):
	/**
	 * Enqueue a CSS file from a Composer package
	 *
	 * Mirrors wp_enqueue_style() but resolves file paths relative to Composer packages.
	 * Automatically detects the assets directory and handles URL generation.
	 *
	 * @param string      $handle       Style handle for registration.
	 * @param string      $calling_file File path to resolve assets relative to. Use __FILE__.
	 * @param string      $file         Relative path to CSS file from assets/ directory.
	 * @param array       $deps         Optional. Array of style handles this style depends on. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param string      $media        Optional. The media for which this stylesheet has been defined. Default 'all'.
	 *
	 * @return bool True on success, false on failure.
	 */
	function wp_enqueue_composer_style(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		string $media = 'all'
	): bool {
		return AssetLoader::enqueue_style( $handle, $calling_file, $file, $deps, $ver, $media );
	}
endif;

if ( ! function_exists( 'wp_register_composer_script' ) ):
	/**
	 * Register a JavaScript file from a Composer package
	 *
	 * Mirrors wp_register_script() but resolves file paths relative to Composer packages.
	 * Automatically detects the assets directory and handles URL generation.
	 * The script can be enqueued later using wp_enqueue_script() with the same handle.
	 *
	 * @param string      $handle       Script handle for registration.
	 * @param string      $calling_file File path to resolve assets relative to. Use __FILE__.
	 * @param string      $file         Relative path to JS file from assets/ directory.
	 * @param array       $deps         Optional. Array of script handles this script depends on. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param bool        $in_footer    Optional. Whether to enqueue the script in the footer. Default true.
	 *
	 * @return bool True on success, false on failure.
	 */
	function wp_register_composer_script(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		bool $in_footer = true
	): bool {
		return AssetLoader::register_script( $handle, $calling_file, $file, $deps, $ver, $in_footer );
	}
endif;

if ( ! function_exists( 'wp_register_composer_style' ) ):
	/**
	 * Register a CSS file from a Composer package
	 *
	 * Mirrors wp_register_style() but resolves file paths relative to Composer packages.
	 * Automatically detects the assets directory and handles URL generation.
	 * The style can be enqueued later using wp_enqueue_style() with the same handle.
	 *
	 * @param string      $handle       Style handle for registration.
	 * @param string      $calling_file File path to resolve assets relative to. Use __FILE__.
	 * @param string      $file         Relative path to CSS file from assets/ directory.
	 * @param array       $deps         Optional. Array of style handles this style depends on. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param string      $media        Optional. The media for which this stylesheet has been defined. Default 'all'.
	 *
	 * @return bool True on success, false on failure.
	 */
	function wp_register_composer_style(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		string $media = 'all'
	): bool {
		return AssetLoader::register_style( $handle, $calling_file, $file, $deps, $ver, $media );
	}
endif;

if ( ! function_exists( 'wp_get_composer_file' ) ):
	/**
	 * Get any file contents from a Composer package
	 *
	 * Generic file loader for any asset type (SVG, JSON, XML, etc).
	 * Optionally sanitizes SVG files for security.
	 *
	 * @param string $calling_file File path to resolve assets relative to. Use __FILE__.
	 * @param string $file         Relative path to file from assets/ directory.
	 * @param bool   $sanitize_svg Optional. Sanitize if file is SVG. Default false.
	 *
	 * @return string|false File content on success, false on failure.
	 */
	function wp_get_composer_file(
		string $calling_file,
		string $file,
		bool $sanitize_svg = false
	) {
		return AssetLoader::get_file( $calling_file, $file, $sanitize_svg );
	}
endif;