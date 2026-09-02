<?php

namespace Jankx\PluginActivation\Plugin;

use Jankx\PluginActivation\Core\Config;
use Jankx\PluginActivation\Core\TgmPluginActivation;

defined('ABSPATH') || exit;

class Downloader
{
    private $core;

    public function __construct(TgmPluginActivation $core)
    {
        $this->core = $core;
    }

    public function getDownloadUrl($slug)
    {
        switch ($this->core->plugins[$slug]['source_type']) {
            case 'repo':
                return $this->getWpRepoDownloadUrl($slug);
            case 'external':
                return $this->core->plugins[$slug]['source'];
            case 'bundled':
                return $this->core->default_path . $this->core->plugins[$slug]['source'];
        }

        return '';
    }

    public function getWpRepoDownloadUrl($slug)
    {
        $source = '';
        $api = $this->core->getPluginsApi($slug);

        if (false !== $api && isset($api->download_link)) {
            $source = $api->download_link;
        }

        return $source;
    }

    public function getPluginsApi($slug)
    {
        static $api = [];

        if (!isset($api[$slug])) {
            if (!function_exists('plugins_api')) {
                require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            }

            $response = plugins_api(
                'plugin_information',
                [
                    'slug'   => $slug,
                    'fields' => [
                        'sections' => false,
                    ],
                ]
            );

            $api[$slug] = false;

            if (is_wp_error($response)) {
                wp_die(esc_html($this->core->strings['oops']));
            } else {
                $api[$slug] = $response;
            }
        }

        return $api[$slug];
    }

    public function getSourceType($source)
    {
        if ('repo' === $source || preg_match(Config::WP_REPO_REGEX, $source)) {
            return 'repo';
        } elseif (preg_match(Config::IS_URL_REGEX, $source)) {
            return 'external';
        } else {
            return 'bundled';
        }
    }
}
