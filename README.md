# Jankx Plugin Activation

A modern, PSR-4 compliant WordPress plugin activation library, refactored from [TGM Plugin Activation](http://tgmpluginactivation.com/).

## Features

- **PSR-4 Compliant**: Clean namespace structure with `Jankx\PluginActivation\`
- **SOLID Principles**: Separated concerns into focused, single-responsibility classes
- **DRY Code**: Extracted repeated logic into reusable methods
- **Backward Compatible**: Works with existing TGMPA code via class aliases
- **Lightweight**: Custom autoloader, no Composer vendor folder required

## Installation

### Manual Installation

1. Download or clone this repository
2. Place it in your theme's `vendor/` directory
3. Include the loader in your `functions.php`:

```php
require_once get_template_directory() . '/vendor/jankx/plugin-activation/src/Bootstrap/loader.php';
```

### Via Composer (Recommended)

```json
{
    "require": {
        "jankx/plugin-activation": "*"
    }
}
```

## Usage

### Basic Usage

```php
add_action('tgmpa_register', 'my_theme_register_required_plugins');

function my_theme_register_required_plugins()
{
    $plugins = [
        [
            'name'     => 'Contact Form 7',
            'slug'     => 'contact-form-7',
            'required' => true,
        ],
        [
            'name'     => 'Yoast SEO',
            'slug'     => 'wordpress-seo',
            'required' => false,
        ],
    ];

    $config = [
        'id'           => 'tgmpa',
        'has_notices'  => true,
        'dismissable'  => true,
        'is_automatic' => false,
    ];

    tgmpa($plugins, $config);
}
```

### Modern API

```php
use Jankx\PluginActivation\Core\TgmPluginActivation;

$instance = TgmPluginActivation::getInstance();
$instance->register([
    'name'     => 'My Plugin',
    'slug'     => 'my-plugin',
    'required' => true,
]);
```

## Architecture

### Namespace Structure

```
Jankx\PluginActivation\
├── Core\
│   ├── Config              # Constants & default strings
│   └── TgmPluginActivation # Main singleton class
├── Plugin\
│   ├── StatusChecker       # Plugin status queries
│   ├── Installer           # Plugin installation & activation
│   └── Downloader          # Plugin download handling
├── Admin\
│   ├── Menu                # WordPress admin menu
│   ├── Notices             # Admin notice messages
│   ├── ActionLinks         # Plugin action link filters
│   └── PageDetector        # Current page detection
├── Url\
│   └── Generator           # URL generation
├── I18n\
│   └── TextDomain          # Internationalization
├── ListTable\
│   └── TgmpaListTable      # WP_List_Table extension
├── BulkInstaller\
│   ├── TgmpaBulkInstaller  # Bulk plugin installer
│   └── TgmpaBulkInstallerSkin
└── Utils\
    └── TgmpaUtils          # Utility functions
```

### Backward Compatibility

The following global aliases are provided for backward compatibility:

```php
// Old class names still work
TGM_Plugin_Activation      → Jankx\PluginActivation\Core\TgmPluginActivation
TGMPA_List_Table           → Jankx\PluginActivation\ListTable\TgmpaListTable
TGMPA_Bulk_Installer       → Jankx\PluginActivation\BulkInstaller\TgmpaBulkInstaller
TGMPA_Bulk_Installer_Skin  → Jankx\PluginActivation\BulkInstaller\TgmpaBulkInstallerSkin
TGMPA_Utils                → Jankx\PluginActivation\Utils\TgmpaUtils

// Global functions
tgmpa($plugins, $config);
load_tgm_plugin_activation();
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | string | `'tgmpa'` | Unique ID for multiple TGMPA instances |
| `default_path` | string | `''` | Default path to bundled plugins |
| `has_notices` | bool | `true` | Show admin notices |
| `dismissable` | bool | `true` | Allow users to dismiss notices |
| `dismiss_msg` | string | `''` | Message when notice is not dismissable |
| `menu` | string | `'tgmpa-install-plugins'` | Menu slug |
| `parent_slug` | string | `'themes.php'` | Parent menu slug |
| `capability` | string | `'edit_theme_options'` | Required capability |
| `is_automatic` | bool | `false` | Auto-activate after install |
| `message` | string | `''` | Message before plugins table |

## Plugin Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | required | Plugin name |
| `slug` | string | required | Plugin slug (folder name) |
| `source` | string | `'repo'` | Plugin source (repo, URL, or path) |
| `required` | bool | `false` | Is plugin required? |
| `version` | string | `''` | Minimum version required |
| `force_activation` | bool | `false` | Force activation |
| `force_deactivation` | bool | `false` | Force deactivation on theme switch |
| `external_url` | string | `''` | External plugin URL |
| `is_callable` | string | `''` | Callable to check if plugin is active |

## License

GPL-2.0 or later

## Credits

- Original library: [TGM Plugin Activation](http://tgmpluginactivation.com/)
- Original authors: Thomas Griffin, Gary Jones, Juliette Reinders Folmer
- Refactored by: Puleeno Nguyen
