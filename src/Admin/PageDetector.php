<?php

namespace Jankx\PluginActivation\Admin;

use Jankx\PluginActivation\Core\TgmPluginActivation;

defined('ABSPATH') || exit;

class PageDetector
{
    private $core;

    public function __construct(TgmPluginActivation $core)
    {
        $this->core = $core;
    }

    public function isTgmpaPage()
    {
        return isset($_GET['page']) && $this->core->menu === $_GET['page'];
    }

    public function isCoreUpdatePage()
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();

        if ('update-core' === $screen->base) {
            return true;
        } elseif ('plugins' === $screen->base && !empty($_POST['action'])) {
            return true;
        } elseif ('update' === $screen->base && !empty($_POST['action'])) {
            return true;
        }

        return false;
    }
}
