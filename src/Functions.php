<?php
/**
 * Asset Registration Helper
 *
 * Provides a simplified interface for registering WordPress scripts and styles.
 * This helper function wraps the RegisterAssets class to provide a quick way to register
 * multiple assets at once with error handling and validation.
 *
 * Example usage:
 * ```php
 * $assets = [
 *     [
 *         'handle' => 'my-script',
 *         'src'    => 'js/script.js',     // Will auto-detect as script
 *         'deps'   => ['jquery'],
 *         'async'  => true
 *     ],
 *     [
 *         'handle' => 'my-style',
 *         'src'    => 'css/style.css',    // Will auto-detect as style
 *         'media'  => 'all'
 *     ]
 * ];
 *
 * $config = [
 *     'debug'          => WP_DEBUG,       // Enable debug mode
 *     'minify'         => true,           // Enable minification
 *     'assets_url'     => 'dist/assets',  // Custom assets directory
 *     'version'        => '1.0.0',        // Asset version
 * ];
 *
 * register_assets( __FILE__, $assets, $config );
 * ```
 *
 * @package     ArrayPress/Utils/Register
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\WP\ComposerAssets\AssetLoader;

if ( ! function_exists( 'wp_enqueue_composer_script' ) ):
	/**
	 * Enqueue a JavaScript file from a Composer library
	 *
	 * @param string      $handle    Script handle for registration
	 * @param string      $file      Relative path to JS file from assets/
	 * @param array       $deps      Optional. Script dependencies. Default ['jquery'].
	 * @param string|bool $ver       Optional. Version string or false for auto-detection. Default false.
	 * @param bool        $in_footer Optional. Whether to load in footer. Default true.
	 *
	 * @return bool True on success, false on failure
	 */
	function wp_enqueue_composer_script(
		string $handle,
		string $file,
		array $deps = [ 'jquery' ],
		$ver = false,
		bool $in_footer = true
	): bool {
		return AssetLoader::enqueue_script( $handle, $file, $deps, $ver, $in_footer );
	}
endif;

if ( ! function_exists( 'wp_enqueue_composer_style' ) ):
	/**
	 * Enqueue a CSS file from a Composer library
	 *
	 * @param string      $handle Style handle for registration
	 * @param string      $file   Relative path to CSS file from assets/
	 * @param array       $deps   Optional. Style dependencies. Default empty array.
	 * @param string|bool $ver    Optional. Version string or false for auto-detection. Default false.
	 * @param string      $media  Optional. Media type for which stylesheet applies. Default 'all'.
	 *
	 * @return bool True on success, false on failure
	 */
	function wp_enqueue_composer_style(
		string $handle,
		string $file,
		array $deps = [],
		$ver = false,
		string $media = 'all'
	): bool {
		return AssetLoader::enqueue_style( $handle, $file, $deps, $ver, $media );
	}
endif;

if ( ! function_exists( 'wp_get_composer_asset_url' ) ):
	/**
	 * Get the URL for an asset file from a Composer library
	 *
	 * @param string $file Relative path to an asset file from assets/
	 *
	 * @return string|null Asset URL or null if not found
	 */
	function wp_get_composer_asset_url( string $file ): ?string {
		return AssetLoader::get_asset_url( $file );
	}
endif;

if ( ! function_exists( 'wp_enqueue_script_from_composer_file' ) ):
	/**
	 * Enqueue a JavaScript file with explicit file reference
	 *
	 * @param string      $handle       Script handle for registration
	 * @param string      $calling_file File path to resolve assets relative to (__FILE__)
	 * @param string      $file         Relative path to JS file from assets/
	 * @param array       $deps         Optional. Script dependencies. Default ['jquery'].
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param bool        $in_footer    Optional. Whether to load in footer. Default true.
	 *
	 * @return bool True on success, false on failure
	 */
	function wp_enqueue_script_from_composer_file(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [ 'jquery' ],
		$ver = false,
		bool $in_footer = true
	): bool {
		return AssetLoader::enqueue_script_from_file( $handle, $calling_file, $file, $deps, $ver, $in_footer );
	}
endif;

if ( ! function_exists( 'wp_enqueue_style_from_composer_file' ) ):
	/**
	 * Enqueue a CSS file with an explicit file reference
	 *
	 * @param string      $handle       Style handle for registration
	 * @param string      $calling_file File path to resolve assets relative to (__FILE__)
	 * @param string      $file         Relative path to CSS file from assets/
	 * @param array       $deps         Optional. Style dependencies. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param string      $media        Optional. Media type for which stylesheet applies. Default 'all'.
	 *
	 * @return bool True on success, false on failure
	 */
	function wp_enqueue_style_from_composer_file(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		string $media = 'all'
	): bool {
		return AssetLoader::enqueue_style_from_file( $handle, $calling_file, $file, $deps, $ver, $media );
	}
endif;