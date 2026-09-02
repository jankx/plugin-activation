<?php

namespace Jankx\PluginActivation\Plugin;

use Jankx\PluginActivation\Core\TgmPluginActivation;

defined('ABSPATH') || exit;

class Installer
{
    private $core;

    public function __construct(TgmPluginActivation $core)
    {
        $this->core = $core;
    }

    public function doPluginInstall()
    {
        if (empty($_GET['plugin'])) {
            return false;
        }

        $slug = $this->core->sanitizeKey(urldecode($_GET['plugin']));

        if (!isset($this->core->plugins[$slug])) {
            return false;
        }

        if ((isset($_GET['tgmpa-install']) && 'install-plugin' === $_GET['tgmpa-install'])
            || (isset($_GET['tgmpa-update']) && 'update-plugin' === $_GET['tgmpa-update'])
        ) {
            return $this->handleInstallOrUpdate($slug);
        } elseif (isset($this->core->plugins[$slug]['file_path'], $_GET['tgmpa-activate'])
            && 'activate-plugin' === $_GET['tgmpa-activate']
        ) {
            return $this->handleActivate($slug);
        }

        return false;
    }

    private function handleInstallOrUpdate($slug)
    {
        $installType = 'install';
        if (isset($_GET['tgmpa-update']) && 'update-plugin' === $_GET['tgmpa-update']) {
            $installType = 'update';
        }

        check_admin_referer('tgmpa-' . $installType, 'tgmpa-nonce');

        $url = wp_nonce_url(
            add_query_arg(
                [
                    'plugin'                 => urlencode($slug),
                    'tgmpa-' . $installType  => $installType . '-plugin',
                ],
                $this->core->urlGenerator->getTgmpaUrl()
            ),
            'tgmpa-' . $installType,
            'tgmpa-nonce'
        );

        $method = '';
        $creds = request_filesystem_credentials(esc_url_raw($url), $method, false, false, []);
        if (false === $creds) {
            return true;
        }

        if (!WP_Filesystem($creds)) {
            request_filesystem_credentials(esc_url_raw($url), $method, true, false, []);
            return true;
        }

        $extra = ['slug' => $slug];
        $source = $this->core->downloader->getDownloadUrl($slug);
        $api = ('repo' === $this->core->plugins[$slug]['source_type'])
            ? $this->core->getPluginsApi($slug)
            : null;
        $api = (false !== $api) ? $api : null;

        $url = add_query_arg(
            [
                'action' => $installType . '-plugin',
                'plugin' => urlencode($slug),
            ],
            'update.php'
        );

        if (!class_exists('Plugin_Upgrader', false)) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        $title = ('update' === $installType)
            ? $this->core->strings['updating']
            : $this->core->strings['installing'];

        $skinArgs = [
            'type'   => ('bundled' !== $this->core->plugins[$slug]['source_type']) ? 'web' : 'upload',
            'title'  => sprintf($title, $this->core->plugins[$slug]['name']),
            'url'    => esc_url_raw($url),
            'nonce'  => $installType . '-plugin_' . $slug,
            'plugin' => '',
            'api'    => $api,
            'extra'  => $extra,
        ];

        if ('update' === $installType) {
            $skinArgs['plugin'] = $this->core->plugins[$slug]['file_path'];
            $skin = new \Plugin_Upgrader_Skin($skinArgs);
        } else {
            $skin = new \Plugin_Installer_Skin($skinArgs);
        }

        $upgrader = new \Plugin_Upgrader($skin);

        add_filter('upgrader_source_selection', [$this->core, 'maybeAdjustSourceDir'], 1, 3);

        if ('update' === $installType) {
            $toInject = [$slug => $this->core->plugins[$slug]];
            $toInject[$slug]['source'] = $source;
            $this->core->injectUpdateInfo($toInject);
            $upgrader->upgrade($this->core->plugins[$slug]['file_path']);
        } else {
            $upgrader->install($source);
        }

        remove_filter('upgrader_source_selection', [$this->core, 'maybeAdjustSourceDir'], 1);

        $this->core->populateFilePath($slug);

        if ($this->core->is_automatic && !$this->core->statusChecker->isActive($slug)) {
            $pluginActivate = $upgrader->plugin_info();
            if (false === $this->activateSinglePlugin($pluginActivate, $slug, true)) {
                return true;
            }
        }

        $this->core->showVersion();

        if ($this->core->statusChecker->isComplete()) {
            echo '<p>', sprintf(
                esc_html($this->core->strings['complete']),
                '<a href="' . esc_url(self_admin_url()) . '">' . esc_html($this->core->strings['dashboard']) . '</a>'
            ), '</p>';
            echo '<style type="text/css">#adminmenu .wp-submenu li.current { display: none !important; }</style>';
        } else {
            echo '<p><a href="', esc_url($this->core->urlGenerator->getTgmpaUrl()), '" target="_parent">',
                esc_html($this->core->strings['return']), '</a></p>';
        }

        return true;
    }

    private function handleActivate($slug)
    {
        check_admin_referer('tgmpa-activate', 'tgmpa-nonce');

        if (false === $this->activateSinglePlugin($this->core->plugins[$slug]['file_path'], $slug)) {
            return true;
        }

        return false;
    }

    public function activateSinglePlugin($file_path, $slug, $automatic = false)
    {
        if ($this->core->statusChecker->canActivate($slug)) {
            $activate = activate_plugin($file_path);

            if (is_wp_error($activate)) {
                echo '<div id="message" class="error"><p>', wp_kses_post($activate->get_error_message()), '</p></div>',
                    '<p><a href="', esc_url($this->core->urlGenerator->getTgmpaUrl()), '" target="_parent">',
                    esc_html($this->core->strings['return']), '</a></p>';
                return false;
            } else {
                if (!$automatic) {
                    if (!isset($_POST['action'])) {
                        echo '<div id="message" class="updated"><p>', esc_html($this->core->strings['activated_successfully']),
                            ' <strong>', esc_html($this->core->plugins[$slug]['name']), '.</strong></p></div>';
                    }
                } else {
                    echo '<p>', esc_html($this->core->strings['plugin_activated']), '</p>';
                }
            }
        } elseif ($this->core->statusChecker->isActive($slug)) {
            echo '<div id="message" class="error"><p>',
                sprintf(
                    esc_html($this->core->strings['plugin_already_active']),
                    '<strong>' . esc_html($this->core->plugins[$slug]['name']) . '</strong>'
                ),
                '</p></div>';
        } elseif ($this->core->statusChecker->requiresUpdate($slug)) {
            if (!$automatic) {
                if (!isset($_POST['action'])) {
                    echo '<div id="message" class="error"><p>',
                        sprintf(
                            esc_html($this->core->strings['plugin_needs_higher_version']),
                            '<strong>' . esc_html($this->core->plugins[$slug]['name']) . '</strong>'
                        ),
                        '</p></div>';
                }
            } else {
                echo '<p>', sprintf(
                    esc_html($this->core->strings['plugin_needs_higher_version']),
                    esc_html($this->core->plugins[$slug]['name'])
                ), '</p>';
            }
        }

        return true;
    }

    public function maybeAdjustSourceDir($source, $remote_source, $upgrader)
    {
        if (!$this->core->pageDetector->isTgmpaPage() || !is_object($GLOBALS['wp_filesystem'])) {
            return $source;
        }

        $sourceFiles = array_keys($GLOBALS['wp_filesystem']->dirlist($remote_source));
        if (1 === count($sourceFiles) && false === $GLOBALS['wp_filesystem']->is_dir($source)) {
            return $source;
        }

        $desiredSlug = '';

        if (false === $upgrader->bulk && !empty($upgrader->skin->options['extra']['slug'])) {
            $desiredSlug = $upgrader->skin->options['extra']['slug'];
        } else {
            foreach ($this->core->plugins as $slug => $plugin) {
                if (!empty($upgrader->skin->plugin_names[$upgrader->skin->i])
                    && $plugin['name'] === $upgrader->skin->plugin_names[$upgrader->skin->i]
                ) {
                    $desiredSlug = $slug;
                    break;
                }
            }
        }

        if (!empty($desiredSlug)) {
            $subdirName = untrailingslashit(str_replace(trailingslashit($remote_source), '', $source));

            if (!empty($subdirName) && $subdirName !== $desiredSlug) {
                $fromPath = untrailingslashit($source);
                $toPath = trailingslashit($remote_source) . $desiredSlug;

                if (true === $GLOBALS['wp_filesystem']->move($fromPath, $toPath)) {
                    return trailingslashit($toPath);
                } else {
                    return new \WP_Error(
                        'rename_failed',
                        esc_html__('The remote plugin package does not contain a folder with the desired slug and renaming did not work.', 'tgmpa') . ' ' . esc_html__('Please contact the plugin provider and ask them to package their plugin according to the WordPress guidelines.', 'tgmpa'),
                        [
                            'found'    => $subdirName,
                            'expected' => $desiredSlug,
                        ]
                    );
                }
            } elseif (empty($subdirName)) {
                return new \WP_Error(
                    'packaged_wrong',
                    esc_html__('The remote plugin package consists of more than one file, but the files are not packaged in a folder.', 'tgmpa') . ' ' . esc_html__('Please contact the plugin provider and ask them to package their plugin according to the WordPress guidelines.', 'tgmpa'),
                    [
                        'found'    => $subdirName,
                        'expected' => $desiredSlug,
                    ]
                );
            }
        }

        return $source;
    }

    public function forceActivation()
    {
        foreach ($this->core->plugins as $slug => $plugin) {
            if (true === $plugin['force_activation']) {
                if (!$this->core->statusChecker->isInstalled($slug)) {
                    continue;
                } elseif ($this->core->statusChecker->canActivate($slug)) {
                    activate_plugin($plugin['file_path']);
                }
            }
        }
    }

    public function forceDeactivation()
    {
        $deactivated = [];

        foreach ($this->core->plugins as $slug => $plugin) {
            if (true === $plugin['force_deactivation'] && is_plugin_active($plugin['file_path'])) {
                deactivate_plugins($plugin['file_path']);
                $deactivated[$plugin['file_path']] = time();
            }
        }

        if (!empty($deactivated)) {
            update_option('recently_activated', $deactivated + (array) get_option('recently_activated'));
        }
    }
}
