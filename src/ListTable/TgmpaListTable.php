<?php

namespace Jankx\PluginActivation\ListTable;

use Jankx\PluginActivation\Core\TgmPluginActivation;
use Jankx\PluginActivation\BulkInstaller\TgmpaBulkInstaller;
use Jankx\PluginActivation\BulkInstaller\TgmpaBulkInstallerSkin;

defined('ABSPATH') || exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class TgmpaListTable extends \WP_List_Table
{
    protected $tgmpa;
    public $view_context = 'all';
    protected $viewTotals = [
        'all'      => 0,
        'install'  => 0,
        'update'   => 0,
        'activate' => 0,
    ];

    public function __construct()
    {
        $this->tgmpa = TgmPluginActivation::getInstance();

        parent::__construct([
            'singular' => 'plugin',
            'plural'   => 'plugins',
            'ajax'     => false,
        ]);

        if (isset($_REQUEST['plugin_status']) && in_array($_REQUEST['plugin_status'], ['install', 'update', 'activate'], true)) {
            $this->view_context = sanitize_key($_REQUEST['plugin_status']);
        }

        add_filter('tgmpa_table_data_items', [$this, 'sortTableItems']);
    }

    public function getTableClasses()
    {
        return ['widefat', 'fixed'];
    }

    protected function _gatherPluginData()
    {
        $this->tgmpa->adminInit();
        $this->tgmpa->notices->thickbox();

        $plugins = $this->categorizePluginsToViews();
        $this->setViewTotals($plugins);

        $tableData = [];
        $i = 0;

        if (empty($plugins[$this->view_context])) {
            $this->view_context = 'all';
        }

        foreach ($plugins[$this->view_context] as $slug => $plugin) {
            $tableData[$i]['sanitized_plugin'] = $plugin['name'];
            $tableData[$i]['slug'] = $slug;
            $tableData[$i]['plugin'] = '<strong>' . $this->tgmpa->urlGenerator->getInfoLink($slug) . '</strong>';
            $tableData[$i]['source'] = $this->getPluginSourceTypeText($plugin['source_type']);
            $tableData[$i]['type'] = $this->getPluginAdviseTypeText($plugin['required']);
            $tableData[$i]['status'] = $this->getPluginStatusText($slug);
            $tableData[$i]['installed_version'] = $this->tgmpa->statusChecker->getInstalledVersion($slug);
            $tableData[$i]['minimum_version'] = $plugin['version'];
            $tableData[$i]['available_version'] = $this->tgmpa->statusChecker->hasUpdate($slug);

            $upgradeNotice = $this->getUpgradeNotice($slug);
            if (!empty($upgradeNotice)) {
                $tableData[$i]['upgrade_notice'] = $upgradeNotice;
                add_action("tgmpa_after_plugin_row_{$slug}", [$this, 'wpPluginUpdateRow'], 10, 2);
            }

            $tableData[$i] = apply_filters('tgmpa_table_data_item', $tableData[$i], $plugin);

            $i++;
        }

        return $tableData;
    }

    protected function categorizePluginsToViews()
    {
        $plugins = [
            'all'      => [],
            'install'  => [],
            'update'   => [],
            'activate' => [],
        ];

        foreach ($this->tgmpa->plugins as $slug => $plugin) {
            if ($this->tgmpa->statusChecker->isFullyUpToDate($slug)) {
                continue;
            }

            $plugins['all'][$slug] = $plugin;

            if (!$this->tgmpa->statusChecker->isInstalled($slug)) {
                $plugins['install'][$slug] = $plugin;
            } else {
                if (false !== $this->tgmpa->statusChecker->hasUpdate($slug)) {
                    $plugins['update'][$slug] = $plugin;
                }

                if ($this->tgmpa->statusChecker->canActivate($slug)) {
                    $plugins['activate'][$slug] = $plugin;
                }
            }
        }

        return $plugins;
    }

    protected function setViewTotals($plugins)
    {
        foreach ($plugins as $type => $list) {
            $this->viewTotals[$type] = count($list);
        }
    }

    protected function getPluginAdviseTypeText($required)
    {
        if (true === $required) {
            return __('Required', 'tgmpa');
        }
        return __('Recommended', 'tgmpa');
    }

    protected function getPluginSourceTypeText($type)
    {
        switch ($type) {
            case 'repo':
                return __('WordPress Repository', 'tgmpa');
            case 'external':
                return __('External Source', 'tgmpa');
            case 'bundled':
                return __('Pre-Packaged', 'tgmpa');
        }
        return '';
    }

    protected function getPluginStatusText($slug)
    {
        if (!$this->tgmpa->statusChecker->isInstalled($slug)) {
            return __('Not Installed', 'tgmpa');
        }

        if (!$this->tgmpa->statusChecker->isActive($slug)) {
            $installStatus = __('Installed But Not Activated', 'tgmpa');
        } else {
            $installStatus = __('Active', 'tgmpa');
        }

        $updateStatus = '';

        if ($this->tgmpa->statusChecker->requiresUpdate($slug) && false === $this->tgmpa->statusChecker->hasUpdate($slug)) {
            $updateStatus = __('Required Update not Available', 'tgmpa');
        } elseif ($this->tgmpa->statusChecker->requiresUpdate($slug)) {
            $updateStatus = __('Requires Update', 'tgmpa');
        } elseif (false !== $this->tgmpa->statusChecker->hasUpdate($slug)) {
            $updateStatus = __('Update recommended', 'tgmpa');
        }

        if ('' === $updateStatus) {
            return $installStatus;
        }

        return sprintf(
            _x('%1$s, %2$s', 'Install/Update Status', 'tgmpa'),
            $installStatus,
            $updateStatus
        );
    }

    protected function getUpgradeNotice($slug)
    {
        if ('repo' !== $this->tgmpa->plugins[$slug]['source_type']) {
            return '';
        }

        $repoUpdates = get_site_transient('update_plugins');

        if (!empty($repoUpdates->response[$this->tgmpa->plugins[$slug]['file_path']]->upgrade_notice)) {
            return $repoUpdates->response[$this->tgmpa->plugins[$slug]['file_path']]->upgrade_notice;
        }

        return '';
    }

    public function sortTableItems($items)
    {
        $type = [];
        $name = [];

        foreach ($items as $i => $plugin) {
            $type[$i] = $plugin['type'];
            $name[$i] = $plugin['sanitized_plugin'];
        }

        array_multisort($type, SORT_DESC, $name, SORT_ASC, $items);

        return $items;
    }

    public function getViews()
    {
        $statusLinks = [];

        foreach ($this->viewTotals as $type => $count) {
            if ($count < 1) {
                continue;
            }

            switch ($type) {
                case 'all':
                    $text = _nx('All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $count, 'plugins', 'tgmpa');
                    break;
                case 'install':
                    $text = _n('To Install <span class="count">(%s)</span>', 'To Install <span class="count">(%s)</span>', $count, 'tgmpa');
                    break;
                case 'update':
                    $text = _n('Update Available <span class="count">(%s)</span>', 'Update Available <span class="count">(%s)</span>', $count, 'tgmpa');
                    break;
                case 'activate':
                    $text = _n('To Activate <span class="count">(%s)</span>', 'To Activate <span class="count">(%s)</span>', $count, 'tgmpa');
                    break;
                default:
                    $text = '';
                    break;
            }

            if (!empty($text)) {
                $statusLinks[$type] = sprintf(
                    '<a href="%s"%s>%s</a>',
                    esc_url($this->tgmpa->urlGenerator->getStatusUrl($type)),
                    ($type === $this->view_context) ? ' class="current"' : '',
                    sprintf($text, number_format_i18n($count))
                );
            }
        }

        return $statusLinks;
    }

    public function columnDefault($item, $columnName)
    {
        return $item[$columnName];
    }

    public function columnCb($item)
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" id="%3$s" />',
            esc_attr($this->_args['singular']),
            esc_attr($item['slug']),
            esc_attr($item['sanitized_plugin'])
        );
    }

    public function columnPlugin($item)
    {
        return sprintf(
            '%1$s %2$s',
            $item['plugin'],
            $this->rowActions($this->getRowActions($item), true)
        );
    }

    public function columnVersion($item)
    {
        $output = [];

        if ($this->tgmpa->statusChecker->isInstalled($item['slug'])) {
            $installed = !empty($item['installed_version']) ? $item['installed_version'] : _x('unknown', 'as in: "version nr unknown"', 'tgmpa');

            $color = '';
            if (!empty($item['minimum_version']) && $this->tgmpa->statusChecker->requiresUpdate($item['slug'])) {
                $color = ' color: #ff0000; font-weight: bold;';
            }

            $output[] = sprintf(
                '<p><span style="min-width: 32px; text-align: right; float: right;%1$s">%2$s</span>' . __('Installed version:', 'tgmpa') . '</p>',
                $color,
                $installed
            );
        }

        if (!empty($item['minimum_version'])) {
            $output[] = sprintf(
                '<p><span style="min-width: 32px; text-align: right; float: right;">%1$s</span>' . __('Minimum required version:', 'tgmpa') . '</p>',
                $item['minimum_version']
            );
        }

        if (!empty($item['available_version'])) {
            $color = '';
            if (!empty($item['minimum_version']) && version_compare($item['available_version'], $item['minimum_version'], '>=')) {
                $color = ' color: #71C671; font-weight: bold;';
            }

            $output[] = sprintf(
                '<p><span style="min-width: 32px; text-align: right; float: right;%1$s">%2$s</span>' . __('Available version:', 'tgmpa') . '</p>',
                $color,
                $item['available_version']
            );
        }

        if (empty($output)) {
            return '&nbsp;';
        }

        return implode("\n", $output);
    }

    public function noItems()
    {
        echo esc_html__('No plugins to install, update or activate.', 'tgmpa') . ' <a href="' . esc_url(self_admin_url()) . '"> ' . esc_html($this->tgmpa->strings['dashboard']) . '</a>';
        echo '<style type="text/css">#adminmenu .wp-submenu li.current { display: none !important; }</style>';
    }

    public function getColumns()
    {
        $columns = [
            'cb'     => '<input type="checkbox" />',
            'plugin' => __('Plugin', 'tgmpa'),
            'source' => __('Source', 'tgmpa'),
            'type'   => __('Type', 'tgmpa'),
        ];

        if ('all' === $this->view_context || 'update' === $this->view_context) {
            $columns['version'] = __('Version', 'tgmpa');
            $columns['status'] = __('Status', 'tgmpa');
        }

        return apply_filters('tgmpa_table_columns', $columns);
    }

    protected function getDefaultPrimaryColumnName()
    {
        return 'plugin';
    }

    protected function getPrimaryColumnName()
    {
        if (method_exists('WP_List_Table', 'get_primary_column_name')) {
            return parent::get_primary_column_name();
        }
        return $this->getDefaultPrimaryColumnName();
    }

    protected function getRowActions($item)
    {
        $actions = [];
        $actionLinks = [];

        if (!$this->tgmpa->statusChecker->isInstalled($item['slug'])) {
            $actions['install'] = __('Install %2$s', 'tgmpa');
        } else {
            if (false !== $this->tgmpa->statusChecker->hasUpdate($item['slug']) && $this->tgmpa->statusChecker->canUpdate($item['slug'])) {
                $actions['update'] = __('Update %2$s', 'tgmpa');
            }

            if ($this->tgmpa->statusChecker->canActivate($item['slug'])) {
                $actions['activate'] = __('Activate %2$s', 'tgmpa');
            }
        }

        foreach ($actions as $action => $text) {
            $nonceUrl = wp_nonce_url(
                add_query_arg(
                    [
                        'plugin'           => urlencode($item['slug']),
                        'tgmpa-' . $action => $action . '-plugin',
                    ],
                    $this->tgmpa->urlGenerator->getTgmpaUrl()
                ),
                'tgmpa-' . $action,
                'tgmpa-nonce'
            );

            $actionLinks[$action] = sprintf(
                '<a href="%1$s">' . esc_html($text) . '</a>',
                esc_url($nonceUrl),
                '<span class="screen-reader-text">' . esc_html($item['sanitized_plugin']) . '</span>'
            );
        }

        $prefix = (defined('WP_NETWORK_ADMIN') && WP_NETWORK_ADMIN) ? 'network_admin_' : '';
        return apply_filters("tgmpa_{$prefix}plugin_action_links", array_filter($actionLinks), $item['slug'], $item, $this->view_context);
    }

    public function singleRow($item)
    {
        echo '<tr class="' . esc_attr('tgmpa-type-' . strtolower($item['type'])) . '">';
        $this->singleRowColumns($item);
        echo '</tr>';

        do_action("tgmpa_after_plugin_row_{$item['slug']}", $item['slug'], $item, $this->view_context);
    }

    public function wpPluginUpdateRow($slug, $item)
    {
        if (empty($item['upgrade_notice'])) {
            return;
        }

        echo '
            <tr class="plugin-update-tr">
                <td colspan="', absint($this->get_column_count()), '" class="plugin-update colspanchange">
                    <div class="update-message">',
                        esc_html__('Upgrade message from the plugin author:', 'tgmpa'),
                        ' <strong>', wp_kses_data($item['upgrade_notice']), '</strong>
                    </div>
                </td>
            </tr>';
    }

    public function extraTablenav($which)
    {
        if ('bottom' === $which) {
            $this->tgmpa->showVersion();
        }
    }

    public function getBulkActions()
    {
        $actions = [];

        if ('update' !== $this->view_context && 'activate' !== $this->view_context) {
            if (current_user_can('install_plugins')) {
                $actions['tgmpa-bulk-install'] = __('Install', 'tgmpa');
            }
        }

        if ('install' !== $this->view_context) {
            if (current_user_can('update_plugins')) {
                $actions['tgmpa-bulk-update'] = __('Update', 'tgmpa');
            }
            if (current_user_can('activate_plugins')) {
                $actions['tgmpa-bulk-activate'] = __('Activate', 'tgmpa');
            }
        }

        return $actions;
    }

    public function processBulkActions()
    {
        if ('tgmpa-bulk-install' === $this->current_action() || 'tgmpa-bulk-update' === $this->current_action()) {
            return $this->processBulkInstallUpdate();
        }

        if ('tgmpa-bulk-activate' === $this->current_action()) {
            return $this->processBulkActivate();
        }

        return false;
    }

    protected function processBulkInstallUpdate()
    {
        check_admin_referer('bulk-' . $this->_args['plural']);

        $installType = ('tgmpa-bulk-update' === $this->current_action()) ? 'update' : 'install';

        if (empty($_POST['plugin'])) {
            $message = ('install' === $installType)
                ? __('No plugins were selected to be installed. No action taken.', 'tgmpa')
                : __('No plugins were selected to be updated. No action taken.', 'tgmpa');

            echo '<div id="message" class="error"><p>', esc_html($message), '</p></div>';
            return false;
        }

        $pluginsToInstall = is_array($_POST['plugin'])
            ? (array) $_POST['plugin']
            : explode(',', $_POST['plugin']);

        $pluginsToInstall = array_map('urldecode', $pluginsToInstall);
        $pluginsToInstall = array_map([$this->tgmpa, 'sanitizeKey'], $pluginsToInstall);

        foreach ($pluginsToInstall as $key => $slug) {
            if (!isset($this->tgmpa->plugins[$slug])) {
                unset($pluginsToInstall[$key]);
                continue;
            }

            if ('install' === $installType && true === $this->tgmpa->statusChecker->isInstalled($slug)) {
                unset($pluginsToInstall[$key]);
            }

            if ('update' === $installType && false === $this->tgmpa->statusChecker->isUpdatable($slug)) {
                unset($pluginsToInstall[$key]);
            }
        }

        if (empty($pluginsToInstall)) {
            $message = ('install' === $installType)
                ? __('No plugins are available to be installed at this time.', 'tgmpa')
                : __('No plugins are available to be updated at this time.', 'tgmpa');

            echo '<div id="message" class="error"><p>', esc_html($message), '</p></div>';
            return false;
        }

        $url = wp_nonce_url(
            $this->tgmpa->urlGenerator->getTgmpaUrl(),
            'bulk-' . $this->_args['plural']
        );

        $_POST['plugin'] = implode(',', $pluginsToInstall);

        $method = '';
        $fields = array_keys($_POST);

        $creds = request_filesystem_credentials(esc_url_raw($url), $method, false, false, $fields);
        if (false === $creds) {
            return true;
        }

        if (!WP_Filesystem($creds)) {
            request_filesystem_credentials(esc_url_raw($url), $method, true, false, $fields);
            return true;
        }

        $names = [];
        $sources = [];
        $filePaths = [];
        $toInject = [];

        foreach ($pluginsToInstall as $slug) {
            $name = $this->tgmpa->plugins[$slug]['name'];
            $source = $this->tgmpa->downloader->getDownloadUrl($slug);

            if (!empty($name) && !empty($source)) {
                $names[] = $name;

                if ('install' === $installType) {
                    $sources[] = $source;
                } else {
                    $filePaths[] = $this->tgmpa->plugins[$slug]['file_path'];
                    $toInject[$slug] = $this->tgmpa->plugins[$slug];
                    $toInject[$slug]['source'] = $source;
                }
            }
        }

        $installer = new TgmpaBulkInstaller(
            new TgmpaBulkInstallerSkin([
                'url'          => esc_url_raw($this->tgmpa->urlGenerator->getTgmpaUrl()),
                'nonce'        => 'bulk-' . $this->_args['plural'],
                'names'        => $names,
                'install_type' => $installType,
            ])
        );

        echo '<div class="tgmpa">',
            '<h2 style="font-size: 23px; font-weight: 400; line-height: 29px; margin: 0; padding: 9px 15px 4px 0;">', esc_html(get_admin_page_title()), '</h2>
            <div class="update-php" style="width: 100%; height: 98%; min-height: 850px; padding-top: 1px;">';

        add_filter('upgrader_source_selection', [$this->tgmpa->installer, 'maybeAdjustSourceDir'], 1, 3);

        if ('tgmpa-bulk-update' === $this->current_action()) {
            $this->tgmpa->injectUpdateInfo($toInject);
            $installer->bulkUpgrade($filePaths);
        } else {
            $installer->bulkInstall($sources);
        }

        remove_filter('upgrader_source_selection', [$this->tgmpa->installer, 'maybeAdjustSourceDir'], 1);

        echo '</div></div>';

        return true;
    }

    protected function processBulkActivate()
    {
        check_admin_referer('bulk-' . $this->_args['plural']);

        if (empty($_POST['plugin'])) {
            echo '<div id="message" class="error"><p>', esc_html__('No plugins were selected to be activated. No action taken.', 'tgmpa'), '</p></div>';
            return false;
        }

        $plugins = array_map('urldecode', (array) $_POST['plugin']);
        $plugins = array_map([$this->tgmpa, 'sanitizeKey'], $plugins);

        $pluginsToActivate = [];
        $pluginNames = [];

        foreach ($plugins as $slug) {
            if ($this->tgmpa->statusChecker->canActivate($slug)) {
                $pluginsToActivate[] = $this->tgmpa->plugins[$slug]['file_path'];
                $pluginNames[] = $this->tgmpa->plugins[$slug]['name'];
            }
        }

        if (empty($pluginsToActivate)) {
            echo '<div id="message" class="error"><p>', esc_html__('No plugins are available to be activated at this time.', 'tgmpa'), '</p></div>';
            return false;
        }

        $activate = activate_plugins($pluginsToActivate);

        if (is_wp_error($activate)) {
            echo '<div id="message" class="error"><p>', wp_kses_post($activate->get_error_message()), '</p></div>';
        } else {
            $count = count($pluginNames);
            $pluginNames = array_map(['\Jankx\PluginActivation\Utils\TgmpaUtils', 'wrapInStrong'], $pluginNames);
            $lastPlugin = array_pop($pluginNames);
            $imploded = empty($pluginNames)
                ? $lastPlugin
                : (implode(', ', $pluginNames) . ' ' . esc_html_x('and', 'plugin A *and* plugin B', 'tgmpa') . ' ' . $lastPlugin);

            printf(
                '<div id="message" class="updated"><p>%1$s %2$s.</p></div>',
                esc_html(_n('The following plugin was activated successfully:', 'The following plugins were activated successfully:', $count, 'tgmpa')),
                $imploded
            );

            $recent = (array) get_option('recently_activated');
            foreach ($pluginsToActivate as $plugin => $time) {
                if (isset($recent[$plugin])) {
                    unset($recent[$plugin]);
                }
            }
            update_option('recently_activated', $recent);
        }

        unset($_POST);

        return true;
    }

    public function prepareItems()
    {
        $columns = $this->getColumns();
        $hidden = [];
        $sortable = [];
        $primary = $this->getPrimaryColumnName();
        $this->_column_headers = [$columns, $hidden, $sortable, $primary];

        if ('tgmpa-bulk-activate' === $this->current_action()) {
            $this->processBulkActions();
        }

        $this->items = apply_filters('tgmpa_table_data_items', $this->_gatherPluginData());
    }
}
