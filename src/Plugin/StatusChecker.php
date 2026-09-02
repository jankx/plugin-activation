<?php

namespace Jankx\PluginActivation\Plugin;

use Jankx\PluginActivation\Core\TgmPluginActivation;

defined('ABSPATH') || exit;

class StatusChecker
{
    private $core;

    public function __construct(TgmPluginActivation $core)
    {
        $this->core = $core;
    }

    public function isInstalled($slug)
    {
        $installedPlugins = $this->core->getPlugins();
        return !empty($installedPlugins[$this->core->plugins[$slug]['file_path']]);
    }

    public function isActive($slug)
    {
        $plugin = $this->core->plugins[$slug];
        return (!empty($plugin['is_callable']) && is_callable($plugin['is_callable']))
            || is_plugin_active($plugin['file_path']);
    }

    public function isFullyUpToDate($slug)
    {
        return $this->isActive($slug) && false === $this->hasUpdate($slug);
    }

    public function canActivate($slug)
    {
        return !$this->isActive($slug) && !$this->requiresUpdate($slug);
    }

    public function canUpdate($slug)
    {
        if ('repo' !== $this->core->plugins[$slug]['source_type']) {
            return true;
        }

        $api = $this->core->getPluginsApi($slug);

        if (false !== $api && isset($api->requires)) {
            return version_compare($this->core->wp_version, $api->requires, '>=');
        }

        return true;
    }

    public function isUpdatable($slug)
    {
        if (!$this->isInstalled($slug)) {
            return false;
        }

        return false !== $this->hasUpdate($slug) && $this->canUpdate($slug);
    }

    public function getInstalledVersion($slug)
    {
        $installedPlugins = $this->core->getPlugins();

        if (!empty($installedPlugins[$this->core->plugins[$slug]['file_path']]['Version'])) {
            return $installedPlugins[$this->core->plugins[$slug]['file_path']]['Version'];
        }

        return '';
    }

    public function requiresUpdate($slug)
    {
        $installedVersion = $this->getInstalledVersion($slug);
        $minimumVersion = $this->core->plugins[$slug]['version'];

        return version_compare($minimumVersion, $installedVersion, '>');
    }

    public function hasUpdate($slug)
    {
        if ('repo' !== $this->core->plugins[$slug]['source_type']) {
            if ($this->requiresUpdate($slug)) {
                return $this->core->plugins[$slug]['version'];
            }
            return false;
        }

        $repoUpdates = get_site_transient('update_plugins');

        if (isset($repoUpdates->response[$this->core->plugins[$slug]['file_path']]->new_version)) {
            return $repoUpdates->response[$this->core->plugins[$slug]['file_path']]->new_version;
        }

        return false;
    }

    public function isComplete()
    {
        foreach ($this->core->plugins as $slug => $plugin) {
            if (!$this->isActive($slug) || false !== $this->hasUpdate($slug)) {
                return false;
            }
        }
        return true;
    }
}
