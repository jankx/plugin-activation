<?php

namespace Jankx\PluginActivation\Core;

defined('ABSPATH') || exit;

class Config
{
    const TGMPA_VERSION = '2.6.1';

    const WP_REPO_REGEX = '|^http[s]?://wordpress\.org/(?:extend/)?plugins/|';

    const IS_URL_REGEX = '|^http[s]?://|';

    public static function getDefaultStrings()
    {
        return [
            'page_title'                      => __('Install Required Plugins', 'tgmpa'),
            'menu_title'                      => __('Install Plugins', 'tgmpa'),
            'installing'                      => __('Installing Plugin: %s', 'tgmpa'),
            'updating'                        => __('Updating Plugin: %s', 'tgmpa'),
            'oops'                            => __('Something went wrong with the plugin API.', 'tgmpa'),
            'notice_can_install_required'     => _n_noop(
                'This theme requires the following plugin: %1$s.',
                'This theme requires the following plugins: %1$s.',
                'tgmpa'
            ),
            'notice_can_install_recommended'  => _n_noop(
                'This theme recommends the following plugin: %1$s.',
                'This theme recommends the following plugins: %1$s.',
                'tgmpa'
            ),
            'notice_ask_to_update'            => _n_noop(
                'The following plugin needs to be updated to its latest version to ensure maximum compatibility with this theme: %1$s.',
                'The following plugins need to be updated to their latest version to ensure maximum compatibility with this theme: %1$s.',
                'tgmpa'
            ),
            'notice_ask_to_update_maybe'      => _n_noop(
                'There is an update available for: %1$s.',
                'There are updates available for the following plugins: %1$s.',
                'tgmpa'
            ),
            'notice_can_activate_required'    => _n_noop(
                'The following required plugin is currently inactive: %1$s.',
                'The following required plugins are currently inactive: %1$s.',
                'tgmpa'
            ),
            'notice_can_activate_recommended' => _n_noop(
                'The following recommended plugin is currently inactive: %1$s.',
                'The following recommended plugins are currently inactive: %1$s.',
                'tgmpa'
            ),
            'install_link'                    => _n_noop(
                'Begin installing plugin',
                'Begin installing plugins',
                'tgmpa'
            ),
            'update_link'                     => _n_noop(
                'Begin updating plugin',
                'Begin updating plugins',
                'tgmpa'
            ),
            'activate_link'                   => _n_noop(
                'Begin activating plugin',
                'Begin activating plugins',
                'tgmpa'
            ),
            'return'                          => __('Return to Required Plugins Installer', 'tgmpa'),
            'dashboard'                       => __('Return to the Dashboard', 'tgmpa'),
            'plugin_activated'                => __('Plugin activated successfully.', 'tgmpa'),
            'activated_successfully'          => __('The following plugin was activated successfully:', 'tgmpa'),
            'plugin_already_active'           => __('No action taken. Plugin %1$s was already active.', 'tgmpa'),
            'plugin_needs_higher_version'     => __('Plugin not activated. A higher version of %s is needed for this theme. Please update the plugin.', 'tgmpa'),
            'complete'                        => __('All plugins installed and activated successfully. %1$s', 'tgmpa'),
            'dismiss'                         => __('Dismiss this notice', 'tgmpa'),
            'notice_cannot_install_activate'  => __('There are one or more required or recommended plugins to install, update or activate.', 'tgmpa'),
            'contact_admin'                   => __('Please contact the administrator of this site for help.', 'tgmpa'),
        ];
    }

    public static function getDefaultConfig()
    {
        return [
            'id'               => 'tgmpa',
            'default_path'     => '',
            'has_notices'      => true,
            'dismissable'      => true,
            'dismiss_msg'      => '',
            'menu'             => 'tgmpa-install-plugins',
            'parent_slug'      => 'themes.php',
            'capability'       => 'edit_theme_options',
            'is_automatic'     => false,
            'message'          => '',
            'strings'          => [],
        ];
    }
}
