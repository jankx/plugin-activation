<?php

namespace Jankx\PluginActivation\Url;

use Jankx\PluginActivation\Core\Config;
use Jankx\PluginActivation\Core\TgmPluginActivation;

defined('ABSPATH') || exit;

class Generator
{
    private $core;

    public function __construct(TgmPluginActivation $core)
    {
        $this->core = $core;
    }

    public function getTgmpaUrl()
    {
        static $url;

        if (!isset($url)) {
            $parent = $this->core->parent_slug;
            if (false === strpos($parent, '.php')) {
                $parent = 'admin.php';
            }
            $url = add_query_arg(
                [
                    'page' => urlencode($this->core->menu),
                ],
                self_admin_url($parent)
            );
        }

        return $url;
    }

    public function getStatusUrl($status)
    {
        return add_query_arg(
            [
                'plugin_status' => urlencode($status),
            ],
            $this->getTgmpaUrl()
        );
    }

    public function getInfoLink($slug)
    {
        if (!empty($this->core->plugins[$slug]['external_url']) && preg_match(Config::IS_URL_REGEX, $this->core->plugins[$slug]['external_url'])) {
            $link = sprintf(
                '<a href="%1$s" target="_blank">%2$s</a>',
                esc_url($this->core->plugins[$slug]['external_url']),
                esc_html($this->core->plugins[$slug]['name'])
            );
        } elseif ('repo' === $this->core->plugins[$slug]['source_type']) {
            $url = add_query_arg(
                [
                    'tab'       => 'plugin-information',
                    'plugin'    => urlencode($slug),
                    'TB_iframe' => 'true',
                    'width'     => '640',
                    'height'    => '500',
                ],
                self_admin_url('plugin-install.php')
            );

            $link = sprintf(
                '<a href="%1$s" class="thickbox">%2$s</a>',
                esc_url($url),
                esc_html($this->core->plugins[$slug]['name'])
            );
        } else {
            $link = esc_html($this->core->plugins[$slug]['name']);
        }

        return $link;
    }
}
