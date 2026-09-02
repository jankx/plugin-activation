<?php

namespace Jankx\PluginActivation\BulkInstaller;

use Jankx\PluginActivation\Core\TgmPluginActivation;

defined('ABSPATH') || exit;

if (!class_exists('Plugin_Upgrader', false)) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
}

class TgmpaBulkInstaller extends \Plugin_Upgrader
{
    public $result;
    public $bulk = false;

    protected $tgmpa;
    protected $clearDestination = false;

    public function __construct($skin = null)
    {
        $this->tgmpa = TgmPluginActivation::getInstance();

        parent::__construct($skin);

        if (isset($this->skin->options['install_type']) && 'update' === $this->skin->options['install_type']) {
            $this->clearDestination = true;
        }

        if ($this->tgmpa->is_automatic) {
            $this->activateStrings();
        }

        add_action('upgrader_process_complete', [$this->tgmpa, 'populateFilePath']);
    }

    public function activateStrings()
    {
        $this->strings['activation_failed'] = __('Plugin activation failed.', 'tgmpa');
        $this->strings['activation_success'] = __('Plugin activated successfully.', 'tgmpa');
    }

    public function run($options)
    {
        $result = parent::run($options);

        if ($this->tgmpa->is_automatic) {
            if ('update' === $this->skin->options['install_type']) {
                $this->upgradeStrings();
            } else {
                $this->installStrings();
            }
        }

        return $result;
    }

    public function bulkInstall($plugins, $args = [])
    {
        add_filter('upgrader_post_install', [$this, 'autoActivate'], 10);

        $defaults = ['clear_update_cache' => true];
        $parsedArgs = wp_parse_args($args, $defaults);

        $this->init();
        $this->bulk = true;
        $this->installStrings();

        $this->skin->header();

        $res = $this->fs_connect([WP_CONTENT_DIR, WP_PLUGIN_DIR]);
        if (!$res) {
            $this->skin->footer();
            return false;
        }

        $this->skin->bulk_header();

        $maintenance = (is_multisite() && !empty($plugins));

        if ($maintenance) {
            $this->maintenance_mode(true);
        }

        $results = [];

        $this->update_count = count($plugins);
        $this->update_current = 0;

        foreach ($plugins as $plugin) {
            $this->update_current++;

            $result = $this->run([
                'package'           => $plugin,
                'destination'       => WP_PLUGIN_DIR,
                'clear_destination' => false,
                'clear_working'     => true,
                'is_multi'          => true,
                'hook_extra'        => ['plugin' => $plugin],
            ]);

            $results[$plugin] = $this->result;

            if (false === $result) {
                break;
            }
        }

        $this->maintenance_mode(false);

        do_action(
            'upgrader_process_complete',
            $this,
            [
                'action'  => 'install',
                'type'    => 'plugin',
                'bulk'    => true,
                'plugins' => $plugins,
            ]
        );

        $this->skin->bulk_footer();
        $this->skin->footer();

        remove_filter('upgrader_post_install', [$this, 'autoActivate'], 10);

        wp_clean_plugins_cache($parsedArgs['clear_update_cache']);

        return $results;
    }

    public function bulkUpgrade($plugins, $args = [])
    {
        add_filter('upgrader_post_install', [$this, 'autoActivate'], 10);

        $result = parent::bulkUpgrade($plugins, $args);

        remove_filter('upgrader_post_install', [$this, 'autoActivate'], 10);

        return $result;
    }

    public function autoActivate($bool)
    {
        if ($this->tgmpa->is_automatic) {
            wp_clean_plugins_cache();

            $pluginInfo = $this->plugin_info();

            if (!is_plugin_active($pluginInfo)) {
                $activate = activate_plugin($pluginInfo);

                $this->strings['process_success'] = $this->strings['process_success'] . "<br />\n";

                if (is_wp_error($activate)) {
                    $this->skin->error($activate);
                    $this->strings['process_success'] .= $this->strings['activation_failed'];
                } else {
                    $this->strings['process_success'] .= $this->strings['activation_success'];
                }
            }
        }

        return $bool;
    }
}
