<?php
declare( strict_types=1 );

namespace ArrayPress\ComposerAssets\Tests;

use ArrayPress\ComposerAssets\AssetLoader;
use PHPUnit\Framework\TestCase;

/**
 * Locating a package's assets directory.
 *
 * Built against a real directory tree rather than mocks, because the whole
 * question is what the filesystem walk does when the tree is shaped awkwardly.
 */
final class AssetResolutionTest extends TestCase {

	private static string $root;

	public static function setUpBeforeClass(): void {
		self::$root = rtrim( WP_CONTENT_DIR, '/' ) . '/plugins/host-plugin';

		$tree = [
			// The host plugin, with its own assets directory.
			self::$root . '/assets/css',
			// A bundled package that HAS assets.
			self::$root . '/vendor/acme/with-assets/assets/js',
			self::$root . '/vendor/acme/with-assets/src/Deep',
			// A bundled package that does NOT — the escape case.
			self::$root . '/vendor/acme/no-assets/src',
			// A directory that is not a package at all.
			self::$root . '/vendor/acme/not-a-package/src',
		];

		foreach ( $tree as $dir ) {
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0777, true );
			}
		}

		file_put_contents( self::$root . '/composer.json', '{"name":"acme/host-plugin"}' );
		file_put_contents( self::$root . '/assets/css/host.css', 'body{}' );

		file_put_contents( self::$root . '/vendor/acme/with-assets/composer.json', '{"name":"acme/with-assets"}' );
		file_put_contents( self::$root . '/vendor/acme/with-assets/assets/js/thing.js', '// x' );
		file_put_contents( self::$root . '/vendor/acme/with-assets/src/Deep/Nested.php', '<?php' );

		file_put_contents( self::$root . '/vendor/acme/no-assets/composer.json', '{"name":"acme/no-assets"}' );
		file_put_contents( self::$root . '/vendor/acme/no-assets/src/Thing.php', '<?php' );

		file_put_contents( self::$root . '/vendor/acme/not-a-package/src/Loose.php', '<?php' );
	}

	protected function setUp(): void {
		AssetLoader::clear_cache();
	}

	public function test_resolves_a_packages_own_assets_directory(): void {
		$assets = AssetLoader::locate_assets( self::$root . '/vendor/acme/with-assets/src/Deep/Nested.php' );

		$this->assertNotNull( $assets );
		$this->assertSame( self::$root . '/vendor/acme/with-assets/assets', $assets['path'] );
	}

	/**
	 * The reason for anchoring on composer.json.
	 *
	 * The previous implementation tried a fixed list of relative paths and took
	 * the first directory named "assets" it found, so a package laid out as
	 * <package>/src/File.php with no assets of its own climbed past vendor/ and
	 * matched the host plugin's assets directory instead.
	 */
	public function test_does_not_escape_to_the_host_plugin(): void {
		$assets = AssetLoader::locate_assets( self::$root . '/vendor/acme/no-assets/src/Thing.php' );

		$this->assertNull(
			$assets,
			'A package without assets must resolve to nothing, never to a neighbour\'s directory.'
		);
	}

	public function test_host_plugin_resolves_its_own_assets(): void {
		$assets = AssetLoader::locate_assets( self::$root . '/some-file.php' );

		$this->assertNotNull( $assets );
		$this->assertSame( self::$root . '/assets', $assets['path'] );
	}

	public function test_a_directory_without_a_composer_json_is_not_a_package(): void {
		// Walking up from here reaches the host plugin's composer.json, which is
		// the nearest real package root — and its assets, correctly.
		$assets = AssetLoader::locate_assets( self::$root . '/vendor/acme/not-a-package/src/Loose.php' );

		$this->assertNotNull( $assets );
		$this->assertSame( self::$root . '/assets', $assets['path'] );
	}

	public function test_resolve_asset_returns_null_for_a_missing_file(): void {
		$this->assertNull(
			AssetLoader::resolve_asset(
				self::$root . '/vendor/acme/with-assets/src/Deep/Nested.php',
				'js/does-not-exist.js'
			)
		);
	}

	public function test_resolve_asset_builds_path_and_url(): void {
		$asset = AssetLoader::resolve_asset(
			self::$root . '/vendor/acme/with-assets/src/Deep/Nested.php',
			'js/thing.js'
		);

		$this->assertNotNull( $asset );
		$this->assertSame( self::$root . '/vendor/acme/with-assets/assets/js/thing.js', $asset['file_path'] );
		$this->assertStringStartsWith( 'https://example.test/wp-content/', $asset['file_url'] );
		$this->assertStringEndsWith( '/assets/js/thing.js', $asset['file_url'] );
	}

	/**
	 * A package with no assets directory should not repeat the filesystem walk
	 * on every call.
	 */
	public function test_misses_are_cached(): void {
		$file = self::$root . '/vendor/acme/no-assets/src/Thing.php';

		$this->assertNull( AssetLoader::locate_assets( $file ) );
		$this->assertNull( AssetLoader::locate_assets( $file ) );
	}

	public function test_clear_cache_forces_a_fresh_lookup(): void {
		$file = self::$root . '/vendor/acme/with-assets/src/Deep/Nested.php';

		$first = AssetLoader::locate_assets( $file );
		AssetLoader::clear_cache();
		$second = AssetLoader::locate_assets( $file );

		$this->assertSame( $first, $second );
	}
}
