<?php
/**
 * WordPress Composer Assets Library
 *
 * Simple, WordPress-native asset loading for Composer libraries.
 * Automatically detects asset directories and handles WordPress-style enqueueing
 * with zero configuration required.
 *
 * @package     ArrayPress\WP\ComposerAssets
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\WP\ComposerAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AssetLoader
 *
 * Core asset management for Composer libraries in WordPress.
 * Handles automatic path detection, URL generation, and WordPress asset registration.
 */
class AssetLoader {

	/**
	 * Cache for resolved asset paths by calling file
	 *
	 * @var array<string, string>
	 */
	private static array $path_cache = [];

	/**
	 * Cache for resolved asset URLs by calling file
	 *
	 * @var array<string, string>
	 */
	private static array $url_cache = [];

	/**
	 * Track enqueued assets for debugging purposes
	 *
	 * @var array<string, array>
	 */
	private static array $enqueued = [];

	/**
	 * Common directory patterns for asset location detection
	 *
	 * @var array<string>
	 */
	private static array $asset_patterns = [
		'/assets',           // Same level as the calling file
		'/../assets',        // Parent directory
		'/../../assets',     // Grandparent (for src/ structures)
		'/../../../assets',  // Great-grandparent (deep src/ structures)
	];

	/**
	 * Find assets directory relative to a calling file
	 *
	 * Uses caching to avoid repeated filesystem operations and automatically
	 * detects common Composer library directory structures.
	 *
	 * @param string $calling_file The file making the call
	 *
	 * @return array|null Array with 'path' and 'url' keys, or null if not found
	 */
	public static function locate_assets( string $calling_file ): ?array {
		$cache_key = $calling_file;

		if ( isset( self::$path_cache[ $cache_key ] ) ) {
			return [
				'path' => self::$path_cache[ $cache_key ],
				'url'  => self::$url_cache[ $cache_key ]
			];
		}

		$file_dir = dirname( $calling_file );

		// Try each pattern until we find a valid assets directory
		foreach ( self::$asset_patterns as $pattern ) {
			$assets_path   = $file_dir . $pattern;
			$resolved_path = realpath( $assets_path );

			if ( $resolved_path && is_dir( $resolved_path ) ) {
				$url = self::path_to_url( $resolved_path );
				if ( $url ) {
					self::$path_cache[ $cache_key ] = $resolved_path;
					self::$url_cache[ $cache_key ]  = $url;

					return [
						'path' => $resolved_path,
						'url'  => $url
					];
				}
			}
		}

		return null;
	}

	/**
	 * Convert a filesystem path to a WordPress URL
	 *
	 * Handles common WordPress directory structures including plugins,
	 * themes, mu-plugins, and content directories.
	 *
	 * @param string $path Filesystem path to convert
	 *
	 * @return string|null Converted URL or null if conversion fails
	 */
	private static function path_to_url( string $path ): ?string {
		$path = wp_normalize_path( $path );

		// Standard WordPress path mappings
		$mappings = [
			wp_normalize_path( WP_CONTENT_DIR ) => content_url(),
			wp_normalize_path( ABSPATH )        => home_url( '/' ),
			wp_normalize_path( WP_PLUGIN_DIR )  => plugins_url(),
		];

		// Add mu-plugins directory if defined
		if ( defined( 'WPMU_PLUGIN_DIR' ) && defined( 'WPMU_PLUGIN_URL' ) ) {
			$mappings[ wp_normalize_path( WPMU_PLUGIN_DIR ) ] = WPMU_PLUGIN_URL;
		}

		foreach ( $mappings as $local_path => $url ) {
			if ( strpos( $path, $local_path ) === 0 ) {
				return str_replace( $local_path, rtrim( $url, '/' ), $path );
			}
		}

		return null;
	}

	/**
	 * Get the calling file with minimal overhead
	 *
	 * Uses debug_backtrace to determine which file is called a global function,
	 * limited to minimal frames for performance.
	 *
	 * @return string|null The calling file path or null if not determinable
	 */
	public static function get_calling_file(): ?string {
		// Only get what we need - 2 frames (skip this method + the wrapper function)
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );

		return $backtrace[1]['file'] ?? null;
	}

	/**
	 * Build file paths from base assets and relative file path
	 *
	 * Utility method to consistently construct file and URL paths
	 * with proper path separator handling.
	 *
	 * @param array  $assets Base assets array with 'path' and 'url' keys
	 * @param string $file   Relative file path
	 *
	 * @return array Array with 'file_path' and 'file_url' keys
	 */
	private static function build_file_paths( array $assets, string $file ): array {
		return [
			'file_path' => $assets['path'] . '/' . ltrim( $file, '/' ),
			'file_url'  => $assets['url'] . '/' . ltrim( $file, '/' )
		];
	}

	/**
	 * Core asset resolution logic
	 *
	 * Combines file detection, path building, and validation into a single
	 * method to eliminate code duplication across functions.
	 *
	 * @param string $file Relative file path from the assets directory
	 *
	 * @return array|null Complete asset information or null if not found
	 */
	public static function resolve_asset( string $file ): ?array {
		$calling_file = self::get_calling_file();
		if ( ! $calling_file ) {
			return null;
		}

		// Locate assets directory
		$assets = self::locate_assets( $calling_file );
		if ( ! $assets ) {
			self::debug_log( "Assets directory not found for {$calling_file}" );

			return null;
		}

		// Build file paths
		$file_paths = self::build_file_paths( $assets, $file );

		// Check if a file exists
		if ( ! file_exists( $file_paths['file_path'] ) ) {
			self::debug_log( "File not found: {$file_paths['file_path']}" );

			return null;
		}

		return array_merge( $assets, $file_paths );
	}

	/**
	 * Resolve asset with an explicit calling file
	 *
	 * Alternative to resolve_asset() that accepts an explicit calling file
	 * to avoid debug_backtrace overhead when the file is known.
	 *
	 * @param string $calling_file The file to resolve assets relative to
	 * @param string $file         Relative file path from the assets directory
	 *
	 * @return array|null Complete asset information or null if not found
	 */
	public static function resolve_asset_from_file( string $calling_file, string $file ): ?array {
		// Locate assets directory
		$assets = self::locate_assets( $calling_file );
		if ( ! $assets ) {
			self::debug_log( "Assets directory not found for {$calling_file}" );

			return null;
		}

		// Build file paths
		$file_paths = self::build_file_paths( $assets, $file );

		// Check if file exists
		if ( ! file_exists( $file_paths['file_path'] ) ) {
			self::debug_log( "File not found: {$file_paths['file_path']}" );

			return null;
		}

		return array_merge( $assets, $file_paths );
	}

	/**
	 * Generate a handle for WordPress assets
	 *
	 * Creates WordPress-compatible handles from file paths.
	 * Relies on WordPress's built-in duplicate prevention rather than
	 * generating unique handles, which is simpler and more predictable.
	 *
	 * @param string $file   File path to generate a handle from
	 * @param string $prefix Handle prefix for namespacing
	 *
	 * @return string WordPress asset handle
	 */
	public static function generate_handle( string $file, string $prefix = 'composer' ): string {
		$base_name = pathinfo( $file, PATHINFO_FILENAME );

		return $prefix . '-' . sanitize_key( $base_name );
	}

	/**
	 * Get file version for cache busting
	 *
	 * Returns file modification time for automatic cache invalidation
	 * or fallback version if file is not accessible.
	 *
	 * @param string $file_path Full path to file
	 *
	 * @return string Version string for WordPress asset registration
	 */
	public static function get_version( string $file_path ): string {
		return file_exists( $file_path ) ? (string) filemtime( $file_path ) : '1.0.0';
	}

	/**
	 * Track enqueued assets for debugging purposes
	 *
	 * Maintains an internal registry of loaded assets for debugging and
	 * troubleshooting. Does not prevent duplicates - WordPress handles that.
	 *
	 * @param string $handle Asset handle
	 * @param string $type   Asset type ('script' or 'style')
	 * @param string $file   Original file path
	 *
	 * @return void
	 */
	public static function track_asset( string $handle, string $type, string $file ): void {
		self::$enqueued[ $handle ] = [
			'type' => $type,
			'file' => $file,
			'time' => time()
		];
	}

	/**
	 * Debug logging that respects WP_DEBUG setting
	 *
	 * Only logs messages when WP_DEBUG is defined and true, preventing
	 * debug noise in production environments.
	 *
	 * @param string $message The message to log
	 *
	 * @return void
	 */
	private static function debug_log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "wp_composer_assets: {$message}" );
		}
	}

	/**
	 * Get debug information about registered assets
	 *
	 * Returns internal state for debugging and troubleshooting asset loading issues.
	 * Only available when WP_DEBUG is enabled.
	 *
	 * @return array|null Debug information or null if WP_DEBUG is disabled
	 */
	public static function get_debug_info(): ?array {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return null;
		}

		return [
			'path_cache' => self::$path_cache,
			'url_cache'  => self::$url_cache,
			'enqueued'   => self::$enqueued,
			'patterns'   => self::$asset_patterns
		];
	}

	/**
	 * Clear all caches and registrations
	 *
	 * Resets internal state, useful for testing or when library locations change.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$path_cache = [];
		self::$url_cache  = [];
		self::$enqueued   = [];
	}

	/**
	 * Enqueue a JavaScript file from a Composer library
	 *
	 * Automatically detects asset location relative to the calling file and
	 * enqueues using WordPress standards with proper versioning and dependencies.
	 *
	 * @param string      $handle    Script handle for registration
	 * @param string      $file      Relative path to JS file from assets/ (e.g., 'js/my-script.js')
	 * @param array       $deps      Optional. Script dependencies. Default ['jquery'].
	 * @param string|bool $ver       Optional. Version string or false for auto-detection. Default false.
	 * @param bool        $in_footer Optional. Whether to load in footer. Default true.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function enqueue_script(
		string $handle,
		string $file,
		array $deps = [ 'jquery' ],
		$ver = false,
		bool $in_footer = true
	): bool {
		// Resolve asset paths
		$asset = self::resolve_asset( $file );
		if ( ! $asset ) {
			return false;
		}

		// Determine version
		$version = ( $ver === false ) ? self::get_version( $asset['file_path'] ) : $ver;

		// Enqueue the script
		wp_enqueue_script( $handle, $asset['file_url'], $deps, $version, $in_footer );

		// Track for debugging
		self::track_asset( $handle, 'script', $file );

		return true;
	}

	/**
	 * Enqueue a CSS file from a Composer library
	 *
	 * Automatically detects asset location relative to the calling file and
	 * enqueues using WordPress standards with proper versioning and dependencies.
	 *
	 * @param string      $handle Style handle for registration
	 * @param string      $file   Relative path to CSS file from assets/ (e.g., 'css/my-style.css')
	 * @param array       $deps   Optional. Style dependencies. Default empty array.
	 * @param string|bool $ver    Optional. Version string or false for auto-detection. Default false.
	 * @param string      $media  Optional. Media type for which stylesheet applies. Default 'all'.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function enqueue_style(
		string $handle,
		string $file,
		array $deps = [],
		$ver = false,
		string $media = 'all'
	): bool {
		// Resolve asset paths
		$asset = self::resolve_asset( $file );
		if ( ! $asset ) {
			return false;
		}

		// Determine version
		$version = ( $ver === false ) ? self::get_version( $asset['file_path'] ) : $ver;

		// Enqueue the style
		wp_enqueue_style( $handle, $asset['file_url'], $deps, $version, $media );

		// Track for debugging
		self::track_asset( $handle, 'style', $file );

		return true;
	}

	/**
	 * Enqueue a JavaScript file with explicit file reference
	 *
	 * Alternative API that accepts a file path explicitly to avoid debug_backtrace
	 * overhead. Use when performance is critical or calling context is complex.
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
	public static function enqueue_script_from_file(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [ 'jquery' ],
		$ver = false,
		bool $in_footer = true
	): bool {
		// Resolve asset paths with an explicit file
		$asset = self::resolve_asset_from_file( $calling_file, $file );
		if ( ! $asset ) {
			return false;
		}

		// Determine version
		$version = ( $ver === false ) ? self::get_version( $asset['file_path'] ) : $ver;

		// Enqueue the script
		wp_enqueue_script( $handle, $asset['file_url'], $deps, $version, $in_footer );

		// Track for debugging
		self::track_asset( $handle, 'script', $file );

		return true;
	}

	/**
	 * Enqueue a CSS file with an explicit file reference
	 *
	 * Alternative API that accepts a file path explicitly to avoid debug_backtrace
	 * overhead. Use when performance is critical or calling context is complex.
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
	public static function enqueue_style_from_file(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		string $media = 'all'
	): bool {
		// Resolve asset paths with an explicit file
		$asset = self::resolve_asset_from_file( $calling_file, $file );
		if ( ! $asset ) {
			return false;
		}

		// Determine version
		$version = ( $ver === false ) ? self::get_version( $asset['file_path'] ) : $ver;

		// Enqueue the style
		wp_enqueue_style( $handle, $asset['file_url'], $deps, $version, $media );

		// Track for debugging
		self::track_asset( $handle, 'style', $file );

		return true;
	}

	/**
	 * Get the URL for an asset file from a Composer library
	 *
	 * Returns the public URL for any asset file without registering or enqueueing it.
	 * Useful for inline references, AJAX endpoints, or custom asset handling.
	 *
	 * @param string $file Relative path to an asset file from assets/
	 *
	 * @return string|null Asset URL or null if not found
	 */
	public static function get_asset_url( string $file ): ?string {
		$asset = self::resolve_asset( $file );

		return $asset ? $asset['file_url'] : null;
	}

}