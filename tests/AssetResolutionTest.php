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

	protected function tearDown(): void {
		unset( $GLOBALS['wp_plugin_paths'] );
	}

	/**
	 * A package that lives outside WordPress altogether, the way a symlinked
	 * plugin's real path does.
	 */
	private static function elsewhere(): string {
		$root = sys_get_temp_dir() . '/wp-composer-assets-tests/elsewhere/real-plugin';

		if ( ! is_dir( $root . '/assets/css' ) ) {
			mkdir( $root . '/assets/css', 0777, true );
		}

		file_put_contents( $root . '/composer.json', '{"name":"acme/real-plugin"}' );
		file_put_contents( $root . '/assets/css/real.css', 'body{}' );
		file_put_contents( $root . '/real-plugin.php', '<?php' );

		return $root;
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
	/**
	 * A relative path that climbs out of the assets directory is refused.
	 *
	 * composer.json sits one level up and does exist, so a null here is the
	 * guard and not a missing file.
	 */
	public function test_a_path_that_escapes_the_assets_directory_is_refused(): void {
		$file = self::$root . '/vendor/acme/with-assets/src/Deep/Nested.php';

		$this->assertNull( AssetLoader::resolve_asset( $file, '../composer.json' ) );
		$this->assertNull( AssetLoader::resolve_asset( $file, 'js/../../composer.json' ) );
		$this->assertNull( AssetLoader::resolve_asset( $file, "js/thing.js\0" ) );
		$this->assertFalse( AssetLoader::get_file( $file, '..\\composer.json' ) );

		// And an ordinary nested path is still fine.
		$this->assertNotNull( AssetLoader::resolve_asset( $file, '/js/thing.js' ) );
	}

	/**
	 * A package outside WordPress has no URL, and says so.
	 */
	public function test_a_path_outside_wordpress_has_no_url(): void {
		$this->assertNull( AssetLoader::locate_assets( self::elsewhere() . '/real-plugin.php' ) );
	}

	/**
	 * A symlinked plugin resolves through core's real-path map.
	 *
	 * __FILE__ resolves symlinks, so a plugin symlinked into wp-content
	 * reports a real path that is nowhere under WordPress, and every asset
	 * silently failed to register. Core keeps a map from the path under
	 * wp-content to the real one -- it is how plugins_url() copes -- and
	 * the same map answers here.
	 */
	public function test_a_symlinked_plugin_resolves_through_the_realpath_map(): void {
		$real = self::elsewhere();

		$GLOBALS['wp_plugin_paths'] = [ WP_CONTENT_DIR . '/plugins/linked-plugin' => $real ];

		$assets = AssetLoader::locate_assets( $real . '/real-plugin.php' );

		$this->assertNotNull( $assets );
		$this->assertSame( $real . '/assets', $assets['path'], 'The path is still the real one; only the URL is mapped.' );
		$this->assertSame( 'https://example.test/wp-content/plugins/linked-plugin/assets', $assets['url'] );

		$asset = AssetLoader::resolve_asset( $real . '/real-plugin.php', 'css/real.css' );

		$this->assertSame( 'https://example.test/wp-content/plugins/linked-plugin/assets/css/real.css', $asset['file_url'] );
	}

	/**
	 * The content directory is replaced once, as a prefix.
	 *
	 * str_replace() rewrote every occurrence, so a path in which the content
	 * directory's name recurred was rewritten twice.
	 */
	public function test_the_url_is_built_from_the_prefix_only(): void {
		$twice = self::$root . '/vendor/acme/twice' . WP_CONTENT_DIR;

		if ( ! is_dir( $twice . '/assets' ) ) {
			mkdir( $twice . '/assets', 0777, true );
		}

		file_put_contents( $twice . '/composer.json', '{"name":"acme/twice"}' );
		file_put_contents( $twice . '/file.php', '<?php' );

		$assets = AssetLoader::locate_assets( $twice . '/file.php' );

		$this->assertNotNull( $assets );
		$this->assertSame(
			'https://example.test/wp-content/plugins/host-plugin/vendor/acme/twice' . WP_CONTENT_DIR . '/assets',
			$assets['url']
		);
	}
}
