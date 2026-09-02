<?php

namespace Jankx\PluginActivation\Admin;

use Jankx\PluginActivation\Core\TgmPluginActivation;

defined('ABSPATH') || exit;

class ActionLinks
{
    private $core;

    public function __construct(TgmPluginActivation $core)
    {
        $this->core = $core;
    }

    public function addFilters()
    {
        foreach ($this->core->plugins as $slug => $plugin) {
            if (false === $this->core->statusChecker->canActivate($slug)) {
                add_filter('plugin_action_links_' . $plugin['file_path'], [$this, 'filterActivate'], 20);
            }

            if (true === $plugin['force_activation']) {
                add_filter('plugin_action_links_' . $plugin['file_path'], [$this, 'filterDeactivate'], 20);
            }

            if (false !== $this->core->statusChecker->requiresUpdate($slug)) {
                add_filter('plugin_action_links_' . $plugin['file_path'], [$this, 'filterUpdate'], 20);
            }
        }
    }

    public function filterActivate($actions)
    {
        unset($actions['activate']);
        return $actions;
    }

    public function filterDeactivate($actions)
    {
        unset($actions['deactivate']);
        return $actions;
    }

    public function filterUpdate($actions)
    {
        $actions['update'] = sprintf(
            '<a href="%1$s" title="%2$s" class="edit">%3$s</a>',
            esc_url($this->core->urlGenerator->getStatusUrl('update')),
            esc_attr__('This plugin needs to be updated to be compatible with your theme.', 'tgmpa'),
            esc_html__('Update Required', 'tgmpa')
        );

        return $actions;
    }

    public function filterInstallActions($installActions)
    {
        if ($this->core->pageDetector->isTgmpaPage()) {
            return false;
        }

        return $installActions;
    }
}
