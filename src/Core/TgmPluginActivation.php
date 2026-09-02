<?php

namespace Jankx\PluginActivation\Core;

use Jankx\PluginActivation\Plugin\StatusChecker;
use Jankx\PluginActivation\Plugin\Downloader;
use Jankx\PluginActivation\Plugin\Installer;
use Jankx\PluginActivation\Admin\Menu;
use Jankx\PluginActivation\Admin\Notices;
use Jankx\PluginActivation\Admin\ActionLinks;
use Jankx\PluginActivation\Admin\PageDetector;
use Jankx\PluginActivation\Url\Generator as UrlGenerator;
use Jankx\PluginActivation\I18n\TextDomain;
use Jankx\PluginActivation\Utils\TgmpaUtils;

defined('ABSPATH') || exit;

class TgmPluginActivation
{
    const TGMPA_VERSION = Config::TGMPA_VERSION;

    public static $instance;

    public $plugins = [];
    public $id = 'tgmpa';
    public $parent_slug = 'themes.php';
    public $capability = 'edit_theme_options';
    public $default_path = '';
    public $has_notices = true;
    public $dismissable = true;
    public $dismiss_msg = '';
    public $is_automatic = false;
    public $message = '';
    public $strings = [];
    public $wp_version;
    public $page_hook;
    public $menu = 'tgmpa-install-plugins';

    protected $sortOrder = [];
    protected $hasForcedActivation = false;
    protected $hasForcedDeactivation = false;

    public $statusChecker;
    public $downloader;
    public $installer;
    public $menuAdmin;
    public $notices;
    public $actionLinks;
    public $pageDetector;
    public $urlGenerator;
    public $textDomain;

    public function __construct()
    {
        $this->wp_version = $GLOBALS['wp_version'];

        $this->statusChecker = new StatusChecker($this);
        $this->downloader = new Downloader($this);
        $this->installer = new Installer($this);
        $this->menuAdmin = new Menu($this);
        $this->notices = new Notices($this);
        $this->actionLinks = new ActionLinks($this);
        $this->pageDetector = new PageDetector($this);
        $this->urlGenerator = new UrlGenerator($this);
        $this->textDomain = new TextDomain();

        do_action_ref_array('tgmpa_init', [$this]);

        add_action('init', [$this->textDomain, 'load'], 5);
        add_filter('load_textdomain_mofile', [$this->textDomain, 'overloadMofile'], 10, 2);
        add_action('init', [$this, 'init']);
    }

    public function __set($name, $value)
    {
        return;
    }

    public function __get($name)
    {
        return $this->{$name};
    }

    public function init()
    {
        if (true !== apply_filters('tgmpa_load', (is_admin() && !defined('DOING_AJAX')))) {
            return;
        }

        $this->strings = array_merge(Config::getDefaultStrings(), $this->strings);

        do_action('tgmpa_register');

        if (empty($this->plugins) || !is_array($this->plugins)) {
            return;
        }

        if (true !== $this->statusChecker->isComplete()) {
            array_multisort($this->sortOrder, SORT_ASC, $this->plugins);

            add_action('admin_menu', [$this->menuAdmin, 'addAdminMenu']);
            add_action('admin_head', [$this->notices, 'dismiss']);

            add_filter('install_plugin_complete_actions', [$this->actionLinks, 'filterInstallActions']);
            add_filter('update_plugin_complete_actions', [$this->actionLinks, 'filterInstallActions']);

            if ($this->has_notices) {
                add_action('admin_notices', [$this->notices, 'render']);
                add_action('admin_init', [$this, 'adminInit'], 1);
                add_action('admin_enqueue_scripts', [$this->notices, 'thickbox']);
            }
        }

        add_action('load-plugins.php', [$this->actionLinks, 'addFilters'], 1);
        add_action('switch_theme', [$this, 'flushPluginsCache']);

        if ($this->has_notices) {
            add_action('switch_theme', [$this->notices, 'updateDismiss']);
        }

        if (true === $this->hasForcedActivation) {
            add_action('admin_init', [$this->installer, 'forceActivation']);
        }

        if (true === $this->hasForcedDeactivation) {
            add_action('switch_theme', [$this->installer, 'forceDeactivation']);
        }

        add_action('admin_head', [$this->menuAdmin, 'adminCss']);
    }

    public function adminInit()
    {
        if (!$this->pageDetector->isTgmpaPage()) {
            return;
        }

        if (isset($_REQUEST['tab']) && 'plugin-information' === $_REQUEST['tab']) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

            wp_enqueue_style('plugin-install');

            global $tab, $body_id;
            $body_id = 'plugin-information';
            $tab = 'plugin-information';

            install_plugin_information();

            exit;
        }
    }

    public function register($plugin)
    {
        if (empty($plugin['slug']) || empty($plugin['name'])) {
            return;
        }

        if (empty($plugin['slug']) || !is_string($plugin['slug']) || isset($this->plugins[$plugin['slug']])) {
            return;
        }

        $defaults = [
            'name'               => '',
            'slug'               => '',
            'source'             => 'repo',
            'required'           => false,
            'version'            => '',
            'force_activation'   => false,
            'force_deactivation' => false,
            'external_url'       => '',
            'is_callable'        => '',
        ];

        $plugin = wp_parse_args($plugin, $defaults);
        $plugin['slug'] = $this->sanitizeKey($plugin['slug']);
        $plugin['version'] = (string) $plugin['version'];
        $plugin['source'] = empty($plugin['source']) ? 'repo' : $plugin['source'];
        $plugin['required'] = TgmpaUtils::validateBool($plugin['required']);
        $plugin['force_activation'] = TgmpaUtils::validateBool($plugin['force_activation']);
        $plugin['force_deactivation'] = TgmpaUtils::validateBool($plugin['force_deactivation']);

        $plugin['file_path'] = $this->_getPluginBasenameFromSlug($plugin['slug']);
        $plugin['source_type'] = $this->downloader->getSourceType($plugin['source']);

        $this->plugins[$plugin['slug']] = $plugin;
        $this->sortOrder[$plugin['slug']] = $plugin['name'];

        if (true === $plugin['force_activation']) {
            $this->hasForcedActivation = true;
        }

        if (true === $plugin['force_deactivation']) {
            $this->hasForcedDeactivation = true;
        }
    }

    public function config($config)
    {
        $keys = [
            'id', 'default_path', 'has_notices', 'dismissable', 'dismiss_msg',
            'menu', 'parent_slug', 'capability', 'is_automatic', 'message', 'strings',
        ];

        foreach ($keys as $key) {
            if (isset($config[$key])) {
                if (is_array($config[$key])) {
                    $this->$key = array_merge($this->$key, $config[$key]);
                } else {
                    $this->$key = $config[$key];
                }
            }
        }
    }

    public function sanitizeKey($key)
    {
        $rawKey = $key;
        $key = preg_replace('`[^A-Za-z0-9_-]`', '', $key);
        return apply_filters('tgmpa_sanitize_key', $key, $rawKey);
    }

    public function getPlugins($pluginFolder = '')
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return get_plugins($pluginFolder);
    }

    public function getPluginsApi($slug)
    {
        return $this->downloader->getPluginsApi($slug);
    }

    public function populateFilePath($pluginSlug = '')
    {
        if (!empty($pluginSlug) && is_string($pluginSlug) && isset($this->plugins[$pluginSlug])) {
            $this->plugins[$pluginSlug]['file_path'] = $this->_getPluginBasenameFromSlug($pluginSlug);
        } else {
            foreach ($this->plugins as $slug => $values) {
                $this->plugins[$slug]['file_path'] = $this->_getPluginBasenameFromSlug($slug);
            }
        }
    }

    protected function _getPluginBasenameFromSlug($slug)
    {
        $keys = array_keys($this->getPlugins());

        foreach ($keys as $key) {
            if (preg_match('|^' . $slug . '/|', $key)) {
                return $key;
            }
        }

        return $slug;
    }

    public function getPluginDataFromName($name, $data = 'slug')
    {
        foreach ($this->plugins as $values) {
            if ($name === $values['name'] && isset($values[$data])) {
                return $values[$data];
            }
        }

        return false;
    }

    public function injectUpdateInfo($plugins)
    {
        $repoUpdates = get_site_transient('update_plugins');

        if (!is_object($repoUpdates)) {
            $repoUpdates = new \stdClass();
        }

        foreach ($plugins as $slug => $plugin) {
            $filePath = $plugin['file_path'];

            if (empty($repoUpdates->response[$filePath])) {
                $repoUpdates->response[$filePath] = new \stdClass();
            }

            $repoUpdates->response[$filePath]->slug = $slug;
            $repoUpdates->response[$filePath]->plugin = $filePath;
            $repoUpdates->response[$filePath]->new_version = $plugin['version'];
            $repoUpdates->response[$filePath]->package = $plugin['source'];
            if (empty($repoUpdates->response[$filePath]->url) && !empty($plugin['external_url'])) {
                $repoUpdates->response[$filePath]->url = $plugin['external_url'];
            }
        }

        set_site_transient('update_plugins', $repoUpdates);
    }

    public function flushPluginsCache($clearUpdateCache = true)
    {
        wp_clean_plugins_cache($clearUpdateCache);
    }

    public function showVersion()
    {
        echo '<p style="float: right; padding: 0em 1.5em 0.5em 0;"><strong><small>',
            esc_html(
                sprintf(
                    __('TGMPA v%s', 'tgmpa'),
                    self::TGMPA_VERSION
                )
            ),
            '</small></strong></p>';
    }

    public static function getInstance()
    {
        if (!isset(self::$instance) && !(self::$instance instanceof self)) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
