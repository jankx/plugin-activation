<?php

namespace Jankx\PluginActivation\Admin;

use Jankx\PluginActivation\Utils\TgmpaUtils;
use Jankx\PluginActivation\Core\TgmPluginActivation;

defined('ABSPATH') || exit;

class Notices
{
    private $core;

    public function __construct(TgmPluginActivation $core)
    {
        $this->core = $core;
    }

    public function render()
    {
        if ($this->core->pageDetector->isTgmpaPage()
            || $this->core->pageDetector->isCoreUpdatePage()
            || get_user_meta(get_current_user_id(), 'tgmpa_dismissed_notice_' . $this->core->id, true)
            || !current_user_can(apply_filters('tgmpa_show_admin_notice_capability', 'publish_posts'))
        ) {
            return;
        }

        $message = [];
        $installLinkCount = 0;
        $updateLinkCount = 0;
        $activateLinkCount = 0;
        $totalRequiredActionCount = 0;

        foreach ($this->core->plugins as $slug => $plugin) {
            if ($this->core->statusChecker->isFullyUpToDate($slug)) {
                continue;
            }

            if (!$this->core->statusChecker->isInstalled($slug)) {
                if (current_user_can('install_plugins')) {
                    $installLinkCount++;
                    if (true === $plugin['required']) {
                        $message['notice_can_install_required'][] = $slug;
                    } else {
                        $message['notice_can_install_recommended'][] = $slug;
                    }
                }
                if (true === $plugin['required']) {
                    $totalRequiredActionCount++;
                }
            } else {
                if (!$this->core->statusChecker->isActive($slug) && $this->core->statusChecker->canActivate($slug)) {
                    if (current_user_can('activate_plugins')) {
                        $activateLinkCount++;
                        if (true === $plugin['required']) {
                            $message['notice_can_activate_required'][] = $slug;
                        } else {
                            $message['notice_can_activate_recommended'][] = $slug;
                        }
                    }
                    if (true === $plugin['required']) {
                        $totalRequiredActionCount++;
                    }
                }

                if ($this->core->statusChecker->requiresUpdate($slug) || false !== $this->core->statusChecker->hasUpdate($slug)) {
                    if (current_user_can('update_plugins')) {
                        $updateLinkCount++;
                        if ($this->core->statusChecker->requiresUpdate($slug)) {
                            $message['notice_ask_to_update'][] = $slug;
                        } elseif (false !== $this->core->statusChecker->hasUpdate($slug)) {
                            $message['notice_ask_to_update_maybe'][] = $slug;
                        }
                    }
                    if (true === $plugin['required']) {
                        $totalRequiredActionCount++;
                    }
                }
            }
        }

        if (!empty($message) || $totalRequiredActionCount > 0) {
            krsort($message);
            $rendered = '';
            $lineTemplate = '<span style="display: block; margin: 0.5em 0.5em 0 0; clear: both;">%s</span>' . "\n";

            if (!current_user_can('activate_plugins') && !current_user_can('install_plugins') && !current_user_can('update_plugins')) {
                $rendered = esc_html($this->core->strings['notice_cannot_install_activate']) . ' ' . esc_html($this->core->strings['contact_admin']);
                $rendered .= $this->createActionLinks(0, 0, 0, $lineTemplate);
            } else {
                if (!$this->core->dismissable && !empty($this->core->dismiss_msg)) {
                    $rendered .= sprintf($lineTemplate, wp_kses_post($this->core->dismiss_msg));
                }

                foreach ($message as $type => $pluginGroup) {
                    $linkedPlugins = [];
                    foreach ($pluginGroup as $pluginSlug) {
                        $linkedPlugins[] = $this->core->urlGenerator->getInfoLink($pluginSlug);
                    }

                    $count = count($linkedPlugins);
                    $linkedPlugins = array_map([TgmpaUtils::class, 'wrapInEm'], $linkedPlugins);
                    $lastPlugin = array_pop($linkedPlugins);
                    $imploded = empty($linkedPlugins)
                        ? $lastPlugin
                        : (implode(', ', $linkedPlugins) . ' ' . esc_html_x('and', 'plugin A *and* plugin B', 'tgmpa') . ' ' . $lastPlugin);

                    $rendered .= sprintf(
                        $lineTemplate,
                        sprintf(
                            translate_nooped_plural($this->core->strings[$type], $count, 'tgmpa'),
                            $imploded,
                            $count
                        )
                    );
                }

                $rendered .= $this->createActionLinks($installLinkCount, $updateLinkCount, $activateLinkCount, $lineTemplate);
            }

            add_settings_error('tgmpa', 'tgmpa', $rendered, $this->getNoticeClass());
        }

        if ('options-general' !== $GLOBALS['current_screen']->parent_base) {
            $this->displaySettingsErrors();
        }
    }

    protected function createActionLinks($installCount, $updateCount, $activateCount, $lineTemplate)
    {
        $actionLinks = [
            'install'  => '',
            'update'   => '',
            'activate' => '',
            'dismiss'  => $this->core->dismissable
                ? '<a href="' . esc_url(wp_nonce_url(
                    add_query_arg('tgmpa-dismiss', 'dismiss_admin_notices'),
                    'tgmpa-dismiss-' . get_current_user_id()
                )) . '" class="dismiss-notice" target="_parent">' . esc_html($this->core->strings['dismiss']) . '</a>'
                : '',
        ];

        $linkTemplate = '<a href="%2$s">%1$s</a>';

        if (current_user_can('install_plugins')) {
            if ($installCount > 0) {
                $actionLinks['install'] = sprintf(
                    $linkTemplate,
                    translate_nooped_plural($this->core->strings['install_link'], $installCount, 'tgmpa'),
                    esc_url($this->core->urlGenerator->getStatusUrl('install'))
                );
            }
            if ($updateCount > 0) {
                $actionLinks['update'] = sprintf(
                    $linkTemplate,
                    translate_nooped_plural($this->core->strings['update_link'], $updateCount, 'tgmpa'),
                    esc_url($this->core->urlGenerator->getStatusUrl('update'))
                );
            }
        }

        if (current_user_can('activate_plugins') && $activateCount > 0) {
            $actionLinks['activate'] = sprintf(
                $linkTemplate,
                translate_nooped_plural($this->core->strings['activate_link'], $activateCount, 'tgmpa'),
                esc_url($this->core->urlGenerator->getStatusUrl('activate'))
            );
        }

        $actionLinks = apply_filters('tgmpa_notice_action_links', $actionLinks);
        $actionLinks = array_filter((array) $actionLinks);

        if (!empty($actionLinks)) {
            $actionLinks = sprintf($lineTemplate, implode(' | ', $actionLinks));
            return apply_filters('tgmpa_notice_rendered_action_links', $actionLinks);
        }

        return '';
    }

    protected function getNoticeClass()
    {
        if (!empty($this->core->strings['nag_type'])) {
            return sanitize_html_class(strtolower($this->core->strings['nag_type']));
        }

        if (version_compare($this->core->wp_version, '4.2', '>=')) {
            return 'notice-warning';
        } elseif (version_compare($this->core->wp_version, '4.1', '>=')) {
            return 'notice';
        } else {
            return 'updated';
        }
    }

    protected function displaySettingsErrors()
    {
        global $wp_settings_errors;

        settings_errors('tgmpa');

        foreach ((array) $wp_settings_errors as $key => $details) {
            if ('tgmpa' === $details['setting']) {
                unset($wp_settings_errors[$key]);
                break;
            }
        }
    }

    public function dismiss()
    {
        if (isset($_GET['tgmpa-dismiss']) && check_admin_referer('tgmpa-dismiss-' . get_current_user_id())) {
            update_user_meta(get_current_user_id(), 'tgmpa_dismissed_notice_' . $this->core->id, 1);
        }
    }

    public function updateDismiss()
    {
        delete_metadata('user', null, 'tgmpa_dismissed_notice_' . $this->core->id, null, true);
    }

    public function thickbox()
    {
        if (!get_user_meta(get_current_user_id(), 'tgmpa_dismissed_notice_' . $this->core->id, true)) {
            add_thickbox();
        }
    }
}
