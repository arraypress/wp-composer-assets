# WordPress Composer Assets

Simple, WordPress-native asset loading for Composer libraries with zero configuration required. Automatically detects asset directories and handles WordPress-style enqueueing without any setup.

## Features

- 🚀 **Zero Configuration** - Just works with standard Composer library structures
- 📁 **Automatic Path Detection** - Finds your assets directory automatically
- 🎯 **WordPress-Native API** - Functions mirror `wp_enqueue_script()` exactly
- ⚡ **Performance Optimized** - Cached path resolution and optional explicit file API
- 🔧 **WP_DEBUG Aware** - Debug logging only when needed
- 🏷️ **Auto Versioning** - File modification time for cache busting
- 🔄 **Mozart Compatible** - Works perfectly with prefixed libraries
- 📦 **PSR-4 Compliant** - Modern PHP standards

## Installation

```bash
composer require arraypress/wp-composer-assets
```

## Basic Usage

### Quick Start (Auto-Detection)

```php
// Enqueue a script - detects assets automatically
wp_enqueue_composer_script(
    'my-library-script',           // handle
    'js/script.js'                 // file path from assets/
);

// Enqueue a stylesheet
wp_enqueue_composer_style(
    'my-library-style',            // handle  
    'css/style.css'                // file path from assets/
);
```

### Explicit File Reference (Recommended for Traits/Classes)

```php
// Use __FILE__ for better performance and reliability
wp_enqueue_script_from_composer_file(
    'my-library-script',           // handle
    __FILE__,                      // calling file (__FILE__)
    'js/script.js',                // file path from assets/
    ['jquery'],                    // dependencies
    false,                         // version (false = auto-detect)
    true                           // in footer
);

wp_enqueue_style_from_composer_file(
    'my-library-style',            // handle
    __FILE__,                      // calling file (__FILE__)
    'css/style.css'                // file path from assets/
);
```

## Directory Structure

The library automatically detects these common Composer library patterns:

```
your-library/
├── assets/                    ← Assets here
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── script.js
├── src/
│   ├── MyClass.php           ← Your PHP files here
│   └── Traits/
│       └── Assets.php
└── composer.json
```

Or deeper structures:

```
your-library/
├── assets/                    ← Assets here  
└── src/
    └── Namespace/
        └── SubNamespace/
            └── MyClass.php    ← PHP files here
```

## Advanced Usage

### Object-Oriented Approach

```php
use ArrayPress\WP\ComposerAssets\AssetLoader;

// Use the class directly for more control
class MyLibraryAssets {
    
    public function enqueue_assets(): void {
        // Enqueue script with dependencies
        $success = AssetLoader::enqueue_script_from_file(
            'my-advanced-script',
            __FILE__,
            'js/advanced.js',
            ['jquery', 'wp-util'],
            '2.1.0',  // explicit version
            true
        );
        
        if ($success) {
            // Localize the script
            wp_localize_script('my-advanced-script', 'myLibraryData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('my_library_nonce')
            ]);
        }
        
        // Enqueue conditional styles
        if (is_admin()) {
            AssetLoader::enqueue_style_from_file(
                'my-admin-style',
                __FILE__,
                'css/admin.css',
                ['wp-admin']
            );
        }
    }
}
```

### Get Asset URLs

```php
// Get asset URL without enqueueing
$logo_url = wp_get_composer_asset_url('images/logo.png');

if ($logo_url) {
    echo '<img src="' . esc_url($logo_url) . '" alt="Logo">';
}
```

### WordPress Integration Example

```php
class MyPlugin {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    public function enqueue_frontend_assets(): void {
        wp_enqueue_script_from_composer_file(
            'my-plugin-frontend',
            __FILE__,
            'js/frontend.js',
            ['jquery']
        );
        
        wp_enqueue_style_from_composer_file(
            'my-plugin-frontend',
            __FILE__,
            'css/frontend.css'
        );
    }
    
    public function enqueue_admin_assets(): void {
        wp_enqueue_script_from_composer_file(
            'my-plugin-admin',
            __FILE__,
            'js/admin.js',
            ['jquery', 'wp-util']
        );
    }
}
```

## API Reference

### Global Functions

| Function | Description |
|----------|-------------|
| `wp_enqueue_composer_script($handle, $file, $deps, $ver, $in_footer)` | Enqueue script with auto-detection |
| `wp_enqueue_composer_style($handle, $file, $deps, $ver, $media)` | Enqueue style with auto-detection |
| `wp_enqueue_script_from_composer_file($handle, $calling_file, $file, ...)` | Enqueue script with explicit file |
| `wp_enqueue_style_from_composer_file($handle, $calling_file, $file, ...)` | Enqueue style with explicit file |
| `wp_get_composer_asset_url($file)` | Get asset URL without enqueueing |

### Class Methods

| Method | Description |
|--------|-------------|
| `AssetLoader::enqueue_script($handle, $file, ...)` | Class method for script enqueueing |
| `AssetLoader::enqueue_style($handle, $file, ...)` | Class method for style enqueueing |
| `AssetLoader::get_asset_url($file)` | Get asset URL |
| `AssetLoader::get_debug_info()` | Get debug information (WP_DEBUG only) |

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$handle` | `string` | WordPress asset handle (required) |
| `$file` | `string` | Path relative to assets/ directory |
| `$calling_file` | `string` | Usually `__FILE__` for explicit functions |
| `$deps` | `array` | Asset dependencies (default: `['jquery']` for scripts) |
| `$ver` | `string\|bool` | Version string or `false` for auto-detection |
| `$in_footer` | `bool` | Load script in footer (default: `true`) |
| `$media` | `string` | CSS media type (default: `'all'`) |

## Performance Tips

1. **Use explicit file functions** in traits and classes for better performance:
   ```php
   // Faster - no debug_backtrace()
   wp_enqueue_script_from_composer_file('handle', __FILE__, 'js/file.js');
   
   // Slower - uses debug_backtrace() 
   wp_enqueue_composer_script('handle', 'js/file.js');
   ```

2. **Cache handles** to avoid repeated calls:
   ```php
   private static $assets_loaded = false;
   
   public function enqueue_assets() {
       if (self::$assets_loaded) return;
       
       wp_enqueue_script_from_composer_file('my-handle', __FILE__, 'js/script.js');
       self::$assets_loaded = true;
   }
   ```

## Debugging

Enable WordPress debug mode to see detailed logging:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Debug information will be logged to help troubleshoot asset loading issues.

## Mozart Integration

Works seamlessly with [Mozart](https://github.com/coenjacobs/mozart) for prefixed libraries:

```php
// After Mozart prefixing, this still works perfectly
\Prefix\wp_enqueue_composer_script('my-handle', 'js/script.js');
```

## Common Use Cases

### WordPress Plugin Development

```php
class MyPlugin {
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }
    
    public function enqueue() {
        wp_enqueue_script_from_composer_file(
            'my-plugin-main', 
            __FILE__, 
            'js/plugin.js'
        );
    }
}
```

### Library Development

```php
trait AssetManager {
    protected function load_library_assets() {
        wp_enqueue_style_from_composer_file(
            'my-library-core',
            __FILE__,
            'css/library.css'
        );
    }
}
```

### Theme Development

```php
// functions.php
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script_from_composer_file(
        'theme-main',
        __FILE__,
        'js/theme.js',
        ['jquery']
    );
});
```

## Requirements

- **PHP:** 7.4 or higher
- **WordPress:** 5.0 or higher

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

Licensed under the GPL-2.0+ License. See LICENSE file for details.

## Support

For support, please use the [issue tracker](https://github.com/arraypress/wp-composer-assets/issues).