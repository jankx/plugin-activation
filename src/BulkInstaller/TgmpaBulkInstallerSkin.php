<?php

namespace Jankx\PluginActivation\BulkInstaller;

use Jankx\PluginActivation\Core\TgmPluginActivation;

defined('ABSPATH') || exit;

if (!class_exists('Bulk_Upgrader_Skin', false)) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skins.php';
}

class TgmpaBulkInstallerSkin extends \Bulk_Upgrader_Skin
{
    public $pluginInfo = [];
    public $pluginNames = [];
    public $i = 0;

    protected $tgmpa;

    public function __construct($args = [])
    {
        $this->tgmpa = TgmPluginActivation::getInstance();

        $defaults = [
            'url'          => '',
            'nonce'        => '',
            'names'        => [],
            'install_type' => 'install',
        ];
        $args = wp_parse_args($args, $defaults);

        $this->pluginNames = $args['names'];

        parent::__construct($args);
    }

    public function addStrings()
    {
        if ('update' === $this->options['install_type']) {
            parent::addStrings();
            $this->upgrader->strings['skin_before_update_header'] = __('Updating Plugin %1$s (%2$d/%3$d)', 'tgmpa');
        } else {
            $this->upgrader->strings['skin_update_failed_error'] = __('An error occurred while installing %1$s: <strong>%2$s</strong>.', 'tgmpa');
            $this->upgrader->strings['skin_update_failed'] = __('The installation of %1$s failed.', 'tgmpa');

            if ($this->tgmpa->is_automatic) {
                $this->upgrader->strings['skin_upgrade_start'] = __('The installation and activation process is starting. This process may take a while on some hosts, so please be patient.', 'tgmpa');
                $this->upgrader->strings['skin_update_successful'] = __('%1$s installed and activated successfully.', 'tgmpa');
                $this->upgrader->strings['skin_upgrade_end'] = __('All installations and activations have been completed.', 'tgmpa');
                $this->upgrader->strings['skin_before_update_header'] = __('Installing and Activating Plugin %1$s (%2$d/%3$d)', 'tgmpa');
            } else {
                $this->upgrader->strings['skin_upgrade_start'] = __('The installation process is starting. This process may take a while on some hosts, so please be patient.', 'tgmpa');
                $this->upgrader->strings['skin_update_successful'] = __('%1$s installed successfully.', 'tgmpa');
                $this->upgrader->strings['skin_upgrade_end'] = __('All installations have been completed.', 'tgmpa');
                $this->upgrader->strings['skin_before_update_header'] = __('Installing Plugin %1$s (%2$d/%3$d)', 'tgmpa');
            }

            if (version_compare($this->tgmpa->wp_version, '4.8', '<')) {
                $this->upgrader->strings['skin_update_successful'] .= ' <a href="#" class="hide-if-no-js" onclick="%2$s"><span>' . esc_html__('Show Details', 'tgmpa') . '</span><span class="hidden">' . esc_html__('Hide Details', 'tgmpa') . '</span>.</a>';
            }
        }
    }

    public function before($title = '')
    {
        if (empty($title)) {
            $title = esc_html($this->pluginNames[$this->i]);
        }
        parent::before($title);
    }

    public function after($title = '')
    {
        if (empty($title)) {
            $title = esc_html($this->pluginNames[$this->i]);
        }
        parent::after($title);

        $this->i++;
    }

    public function bulkFooter()
    {
        parent::bulkFooter();

        wp_clean_plugins_cache();

        $this->tgmpa->showVersion();

        $updateActions = [];

        if ($this->tgmpa->statusChecker->isComplete()) {
            echo '<style type="text/css">#adminmenu .wp-submenu li.current { display: none !important; }</style>';
            $updateActions['dashboard'] = sprintf(
                esc_html($this->tgmpa->strings['complete']),
                '<a href="' . esc_url(self_admin_url()) . '">' . esc_html($this->tgmpa->strings['dashboard']) . '</a>'
            );
        } else {
            $updateActions['tgmpa_page'] = '<a href="' . esc_url($this->tgmpa->urlGenerator->getTgmpaUrl()) . '" target="_parent">' . esc_html($this->tgmpa->strings['return']) . '</a>';
        }

        $updateActions = apply_filters('tgmpa_update_bulk_plugins_complete_actions', $updateActions, $this->pluginInfo);

        if (!empty($updateActions)) {
            $this->feedback(implode(' | ', (array) $updateActions));
        }
    }
}
