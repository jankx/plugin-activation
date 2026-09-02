<?php

namespace Jankx\PluginActivation\I18n;

defined('ABSPATH') || exit;

class TextDomain
{
    public function load()
    {
        if (is_textdomain_loaded('tgmpa')) {
            return;
        }

        $languagesDir = dirname(__DIR__, 2) . '/languages';

        if (false !== strpos(__FILE__, WP_PLUGIN_DIR) || false !== strpos(__FILE__, WPMU_PLUGIN_DIR)) {
            add_action('load_textdomain_mofile', [$this, 'correctPluginMofile'], 10, 2);
            load_theme_textdomain('tgmpa', $languagesDir);
            remove_action('load_textdomain_mofile', [$this, 'correctPluginMofile'], 10);
        } else {
            load_theme_textdomain('tgmpa', $languagesDir);
        }
    }

    public function correctPluginMofile($mofile, $domain)
    {
        if ('tgmpa' !== $domain) {
            return $mofile;
        }
        return preg_replace('`/([a-z]{2}_[A-Z]{2}.mo)$`', '/tgmpa-$1', $mofile);
    }

    public function overloadMofile($mofile, $domain)
    {
        if ('tgmpa' !== $domain || false === strpos($mofile, WP_LANG_DIR) || @is_readable($mofile)) {
            return $mofile;
        }

        if (false !== strpos($mofile, '/themes/')) {
            return str_replace('/themes/', '/plugins/', $mofile);
        } elseif (false !== strpos($mofile, '/plugins/')) {
            return str_replace('/plugins/', '/themes/', $mofile);
        }

        return $mofile;
    }
}
