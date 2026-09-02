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

        $packageLanguagesDir = dirname(__DIR__, 2) . '/languages';
        $themeLanguagesDir = get_stylesheet_directory() . '/languages';

        // Try theme languages first (for custom translations)
        if (is_dir($themeLanguagesDir)) {
            load_theme_textdomain('tgmpa', $themeLanguagesDir);
        }

        // Load from package languages as fallback
        if (is_dir($packageLanguagesDir)) {
            add_action('load_textdomain_mofile', [$this, 'correctPluginMofile'], 10, 2);
            load_theme_textdomain('tgmpa', $packageLanguagesDir);
            remove_action('load_textdomain_mofile', [$this, 'correctPluginMofile'], 10);
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
