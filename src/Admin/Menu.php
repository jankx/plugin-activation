<?php

namespace Jankx\PluginActivation\Admin;

use Jankx\PluginActivation\ListTable\TgmpaListTable;
use Jankx\PluginActivation\Core\TgmPluginActivation;

defined('ABSPATH') || exit;

class Menu
{
    private $core;

    public function __construct(TgmPluginActivation $core)
    {
        $this->core = $core;
    }

    public function addAdminMenu()
    {
        if (!current_user_can('install_plugins')) {
            return;
        }

        $args = apply_filters(
            'tgmpa_admin_menu_args',
            [
                'parent_slug' => $this->core->parent_slug,
                'page_title'  => $this->core->strings['page_title'],
                'menu_title'  => $this->core->strings['menu_title'],
                'capability'  => $this->core->capability,
                'menu_slug'   => $this->core->menu,
                'function'    => [$this, 'installPluginsPage'],
            ]
        );

        $this->addSubMenu($args);
    }

    protected function addSubMenu(array $args)
    {
        if (has_filter('tgmpa_admin_menu_use_add_theme_page')) {
            _deprecated_function(
                'The "tgmpa_admin_menu_use_add_theme_page" filter',
                '2.5.0',
                esc_html__('Set the parent_slug config variable instead.', 'tgmpa')
            );
        }

        if ('themes.php' === $this->core->parent_slug) {
            $this->core->page_hook = call_user_func(
                'add_theme_page',
                $args['page_title'],
                $args['menu_title'],
                $args['capability'],
                $args['menu_slug'],
                $args['function']
            );
        } else {
            $this->core->page_hook = call_user_func(
                'add_submenu_page',
                $args['parent_slug'],
                $args['page_title'],
                $args['menu_title'],
                $args['capability'],
                $args['menu_slug'],
                $args['function']
            );
        }
    }

    public function installPluginsPage()
    {
        $pluginTable = new TgmpaListTable();

        if ((('tgmpa-bulk-install' === $pluginTable->current_action()
                || 'tgmpa-bulk-update' === $pluginTable->current_action())
            && $pluginTable->processBulkActions())
            || $this->core->installer->doPluginInstall()
        ) {
            return;
        }

        wp_clean_plugins_cache(false);
        ?>
        <div class="tgmpa wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php $pluginTable->prepareItems(); ?>

            <?php if (!empty($this->core->message) && is_string($this->core->message)) : ?>
                <?php echo wp_kses_post($this->core->message); ?>
            <?php endif; ?>

            <?php $pluginTable->views(); ?>

            <form id="tgmpa-plugins" action="" method="post">
                <input type="hidden" name="tgmpa-page" value="<?php echo esc_attr($this->core->menu); ?>" />
                <input type="hidden" name="plugin_status" value="<?php echo esc_attr($pluginTable->view_context); ?>" />
                <?php $pluginTable->display(); ?>
            </form>
        </div>
        <?php
    }

    public function adminCss()
    {
        if (!$this->core->pageDetector->isTgmpaPage()) {
            return;
        }

        echo '
        <style>
        #tgmpa-plugins .tgmpa-type-required > th {
            border-left: 3px solid #dc3232;
        }
        </style>';
    }
}
