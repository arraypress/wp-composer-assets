<?php
/**
 * Asset Loader Class
 *
 * Handles loading and registration of assets from Composer packages.
 *
 * @package     ArrayPress\WP\ComposerAssets
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\ComposerAssets;

class AssetLoader {

	/**
	 * Common directory patterns for asset location detection
	 *
	 * @var array<string>
	 */
	private static array $asset_patterns = [
		'/assets',                // Same level as the calling file
		'/../assets',             // Parent directory
		'/../../assets',          // Grandparent (for src/ structures)
		'/../../../assets',       // Great-grandparent (deep src/ structures)
		'/../../../../assets',    // Great-great-grandparent (very deep nesting)
		'/../../../../../assets', // Great-great-great-grandparent (extremely deep nesting)
	];

	/**
	 * Cached asset paths for performance
	 *
	 * @var array<string, string>
	 */
	private static array $path_cache = [];

	/**
	 * Cached asset URLs for performance
	 *
	 * @var array<string, string>
	 */
	private static array $url_cache = [];

	/**
	 * Enqueue a JavaScript file from a Composer package
	 *
	 * @param string      $handle       Script handle for registration
	 * @param string      $calling_file File path to resolve assets relative to
	 * @param string      $file         Relative path to JS file from assets/
	 * @param array       $deps         Optional. Script dependencies. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param bool        $in_footer    Optional. Whether to load in footer. Default true.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function enqueue_script(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		bool $in_footer = true
	): bool {
		if ( ! self::register_script( $handle, $calling_file, $file, $deps, $ver, $in_footer ) ) {
			return false;
		}

		wp_enqueue_script( $handle );

		return true;
	}

	/**
	 * Enqueue a CSS file from a Composer package
	 *
	 * @param string      $handle       Style handle for registration
	 * @param string      $calling_file File path to resolve assets relative to
	 * @param string      $file         Relative path to CSS file from assets/
	 * @param array       $deps         Optional. Style dependencies. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param string      $media        Optional. Media type. Default 'all'.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function enqueue_style(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		string $media = 'all'
	): bool {
		if ( ! self::register_style( $handle, $calling_file, $file, $deps, $ver, $media ) ) {
			return false;
		}

		wp_enqueue_style( $handle );

		return true;
	}

	/**
	 * Register a JavaScript file from a Composer package
	 *
	 * @param string      $handle       Script handle for registration
	 * @param string      $calling_file File path to resolve assets relative to
	 * @param string      $file         Relative path to JS file from assets/
	 * @param array       $deps         Optional. Script dependencies. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param bool        $in_footer    Optional. Whether to load in footer. Default true.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function register_script(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		bool $in_footer = true
	): bool {
		$asset = self::resolve_asset( $calling_file, $file );
		if ( ! $asset ) {
			return false;
		}

		$version = ( $ver === false ) ? self::get_version( $asset['file_path'] ) : $ver;

		wp_register_script( $handle, $asset['file_url'], $deps, $version, $in_footer );

		return true;
	}

	/**
	 * Register a CSS file from a Composer package
	 *
	 * @param string      $handle       Style handle for registration
	 * @param string      $calling_file File path to resolve assets relative to
	 * @param string      $file         Relative path to CSS file from assets/
	 * @param array       $deps         Optional. Style dependencies. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param string      $media        Optional. Media type. Default 'all'.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function register_style(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		string $media = 'all'
	): bool {
		$asset = self::resolve_asset( $calling_file, $file );
		if ( ! $asset ) {
			return false;
		}

		$version = ( $ver === false ) ? self::get_version( $asset['file_path'] ) : $ver;

		wp_register_style( $handle, $asset['file_url'], $deps, $version, $media );

		return true;
	}

	/**
	 * Get file contents from a Composer package
	 *
	 * Generic file loader for any asset type (SVG, JSON, XML, etc).
	 * Optionally sanitizes SVG files.
	 *
	 * @param string $calling_file File path to resolve assets relative to
	 * @param string $file         Relative path to file from assets/ directory
	 * @param bool   $sanitize_svg Optional. Sanitize if file is SVG. Default false.
	 *
	 * @return string|false File content or false on failure
	 */
	public static function get_file( string $calling_file, string $file, bool $sanitize_svg = false ) {
		$asset = self::resolve_asset( $calling_file, $file );

		if ( ! $asset || ! isset( $asset['file_path'] ) ) {
			return false;
		}

		$content = file_get_contents( $asset['file_path'] );

		if ( $content === false ) {
			return false;
		}

		// Auto-sanitize SVG files if requested
		if ( $sanitize_svg && str_ends_with( strtolower( $file ), '.svg' ) ) {
			$content = self::sanitize_svg( $content );
		}

		return $content;
	}

	/**
	 * Resolve asset paths from calling file
	 *
	 * @param string $calling_file The file to resolve assets relative to
	 * @param string $file         Relative file path from the assets directory
	 *
	 * @return array|null Complete asset information or null if not found
	 */
	public static function resolve_asset( string $calling_file, string $file ): ?array {
		$assets = self::locate_assets( $calling_file );
		if ( ! $assets ) {
			return null;
		}

		$file_paths = self::build_file_paths( $assets, $file );

		if ( ! file_exists( $file_paths['file_path'] ) ) {
			return null;
		}

		return array_merge( $assets, $file_paths );
	}

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
	 * Build file paths from base assets and relative file path
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
	 * Convert filesystem path to URL
	 *
	 * @param string $path Absolute filesystem path
	 *
	 * @return string|null URL or null if conversion fails
	 */
	private static function path_to_url( string $path ): ?string {
		$path        = wp_normalize_path( $path );
		$content_dir = wp_normalize_path( WP_CONTENT_DIR );
		$content_url = content_url();

		// Check if path is within content directory
		if ( str_starts_with( $path, $content_dir ) ) {
			return str_replace( $content_dir, $content_url, $path );
		}

		// Check if path is within ABSPATH
		$abspath = wp_normalize_path( ABSPATH );
		if ( str_starts_with( $path, $abspath ) ) {
			return str_replace( $abspath, site_url( '/' ), $path );
		}

		return null;
	}

	/**
	 * Get file version based on modification time
	 *
	 * @param string $file_path Absolute path to file
	 *
	 * @return string Version string (timestamp)
	 */
	private static function get_version( string $file_path ): string {
		if ( file_exists( $file_path ) ) {
			return (string) filemtime( $file_path );
		}

		return '1.0.0';
	}

	/**
	 * Sanitize SVG string
	 *
	 * Basic cleanup for trusted SVG files.
	 *
	 * @param string $svg SVG string
	 *
	 * @return string Sanitized SVG content
	 */
	private static function sanitize_svg( string $svg ): string {
		// Basic security sanitization
		$svg = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $svg );
		$svg = preg_replace( '/\s*on\w+\s*=\s*["\'].*?["\']/i', '', $svg );

		// Cleanup
		$svg = preg_replace( '/<!--(.|\s)*?-->/', '', $svg );        // Remove comments
		$svg = preg_replace( '/\s+/', ' ', $svg );                   // Normalize whitespace
		$svg = preg_replace( '/>\s+</', '><', $svg );                // Remove spaces between tags
		$svg = str_replace( 'viewbox=', 'viewBox=', $svg );              // Fix casing

		return trim( $svg );
	}

	/**
	 * Clear all caches
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$path_cache = [];
		self::$url_cache  = [];
	}

}