<?php

defined('ABSPATH') || exit;

use Jankx\PluginActivation\Core\TgmPluginActivation;

// PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Jankx\\PluginActivation\\';
    $baseDir = __DIR__ . '/../';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

if (!function_exists('load_tgm_plugin_activation')) {
    function load_tgm_plugin_activation()
    {
        $GLOBALS['tgmpa'] = TgmPluginActivation::getInstance();
    }
}

if (did_action('plugins_loaded')) {
    load_tgm_plugin_activation();
} else {
    add_action('plugins_loaded', 'load_tgm_plugin_activation');
}

if (!function_exists('tgmpa')) {
    function tgmpa($plugins, $config = [])
    {
        $instance = TgmPluginActivation::getInstance();

        foreach ($plugins as $plugin) {
            $instance->register($plugin);
        }

        if (!empty($config) && is_array($config)) {
            if (isset($config['notices'])) {
                _deprecated_argument(__FUNCTION__, '2.2.0', 'The `notices` config parameter was renamed to `has_notices` in TGMPA 2.2.0. Please adjust your configuration.');
                if (!isset($config['has_notices'])) {
                    $config['has_notices'] = $config['notices'];
                }
            }

            if (isset($config['parent_menu_slug'])) {
                _deprecated_argument(__FUNCTION__, '2.4.0', 'The `parent_menu_slug` config parameter was removed in TGMPA 2.4.0.');
            }
            if (isset($config['parent_url_slug'])) {
                _deprecated_argument(__FUNCTION__, '2.4.0', 'The `parent_url_slug` config parameter was removed in TGMPA 2.4.0.');
            }

            $instance->config($config);
        }
    }
}

// Backward compatible stubs
if (!class_exists('TGM_Bulk_Installer')) {
    class TGM_Bulk_Installer {}
}

if (!class_exists('TGM_Bulk_Installer_Skin')) {
    class TGM_Bulk_Installer_Skin {}
}

// Backward compatible class aliases
class_alias(TgmPluginActivation::class, 'TGM_Plugin_Activation');
class_alias(\Jankx\PluginActivation\ListTable\TgmpaListTable::class, 'TGMPA_List_Table');
class_alias(\Jankx\PluginActivation\BulkInstaller\TgmpaBulkInstaller::class, 'TGMPA_Bulk_Installer');
class_alias(\Jankx\PluginActivation\BulkInstaller\TgmpaBulkInstallerSkin::class, 'TGMPA_Bulk_Installer_Skin');
class_alias(\Jankx\PluginActivation\Utils\TgmpaUtils::class, 'TGMPA_Utils');
