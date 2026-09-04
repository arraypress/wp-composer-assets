# Composer Assets

Enqueue a CSS or JS file that lives inside a Composer package, without
knowing where Composer put it.

## What it does

`plugins_url()` works from a plugin file. It does not work from a library in
`vendor/`, because the library has no idea whether it was installed there, in
`vendor-prefixed/`, symlinked from a path repository, or bundled somewhere
else entirely.

Every library that ships an asset ends up writing the same fragile path
arithmetic. This resolves it from `__FILE__` instead, and hands the URL to
`wp_enqueue_script()` the normal way.

## Features

* Enqueue a script or style from inside a library, wherever it was installed
* Register one for later, when the handle is needed before the screen is known
* Keep working when the package has been prefixed into `vendor-prefixed/`
* Get the URL or path of any packaged file, not just CSS and JS
* Version assets by file modification time, so a change busts the cache
* Resolve without configuration — no base path or URL to pass in

## Installation

```bash
composer require arraypress/wp-composer-assets
```

## Quick start

From inside a library that ships `assets/js/field-kit.js`:

```php
add_action( 'admin_enqueue_scripts', function () {
	arraypress_enqueue_composer_script(
		'field-kit',
		__FILE__,
		'js/field-kit.js',
		[ 'jquery' ],
		'1.0.0'
	);
} );
```

`__FILE__` is the whole trick: the package root is found relative to the file
asking, so the same call works from `vendor/`, from `vendor-prefixed/` and
from a symlinked checkout.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
