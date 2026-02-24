<?php
/**
 * Plugin Name: TruCookie CMP (Consent Mode v2)
 * Plugin URI: https://trucookie.pro
 * Description: Connects your WordPress site to the TruCookie dashboard. Installs the CMP snippet, provides best-effort verification, and helps run audits.
 * Version: 0.1.1
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Author: TruCookie
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: trucookie-cmp-consent-mode-v2
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/Traits/CoreConfigTrait.php';
require_once __DIR__ . '/includes/Traits/ApiClientTrait.php';
require_once __DIR__ . '/includes/Traits/AdminActionsBannerTrait.php';
require_once __DIR__ . '/includes/Traits/AdminPageTrait.php';


final class SaaS_Cookie_CMP_Plugin
{
    private const PLUGIN_FILE = __FILE__;

    /**
     * Default dashboard base URL (used when no override is provided).
     *
     * Override options:
     * - wp-config.php: define('SC_SERVICE_URL', 'https://your-dashboard-domain.com');
     * - WP filter: add_filter('sc_default_service_url', fn() => 'https://...');
     */
    private const DEFAULT_SERVICE_URL = 'https://trucookie.pro';
    private const LEGACY_SERVICE_HOSTS = ['cmp.markets', 'www.cmp.markets'];

    private const OPT_SERVICE_URL = 'sc_service_url';
    private const OPT_API_KEY = 'sc_api_key';
    private const OPT_SITE_PUBLIC_ID = 'sc_site_public_id';
    private const OPT_VERIFICATION_TOKEN = 'sc_verification_token';
    private const OPT_INJECT_BANNER = 'sc_inject_banner';
    private const OPT_INJECT_META = 'sc_inject_meta';
    private const OPT_LAST_PLAN = 'sc_last_plan';
    private const OPT_DASHBOARD_SITE_URL = 'sc_dashboard_site_url';
    private const OPT_BANNER_CONFIG = 'sc_banner_config';
    private const OPT_SITE_STATUS = 'sc_site_status';
    private const OPT_LAST_BANNER_CHECK = 'sc_last_banner_check';
    private const OPT_AUTO_SYNC = 'sc_auto_sync_enabled';
    private const OPT_LOCAL_BANNER_DIRTY = 'sc_local_banner_dirty';
    private const OPT_UPGRADE_INTENTS = 'sc_upgrade_intents';

    use SC_CoreConfigTrait;
    use SC_ApiClientTrait;
    use SC_AdminActionsBannerTrait;
    use SC_AdminPageTrait;


    public static function init(): void
    {
        $self = new self();

        add_action('admin_menu', [$self, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$self, 'admin_enqueue_scripts']);
        add_action('admin_post_sc_connect', [$self, 'handle_connect']);
        add_action('admin_post_sc_disconnect', [$self, 'handle_disconnect']);
        add_action('admin_post_sc_save_toggles', [$self, 'handle_save_toggles']);
        add_action('admin_post_sc_verify', [$self, 'handle_verify']);
        add_action('admin_post_sc_sync_site', [$self, 'handle_sync_site']);
        add_action('admin_post_sc_check_banner', [$self, 'handle_check_banner']);
        add_action('admin_post_sc_run_light_scan', [$self, 'handle_run_light_scan']);
        add_action('admin_post_sc_run_deep_scan', [$self, 'handle_run_deep_scan']);
        add_action('admin_post_sc_pull_banner_config', [$self, 'handle_pull_banner_config']);
        add_action('admin_post_sc_save_banner_config', [$self, 'handle_save_banner_config']);
        add_action('admin_post_sc_track_upgrade_intent', [$self, 'handle_track_upgrade_intent']);

        // Snippet + meta tag injection (runs early in <head>).
        add_action('wp_head', [$self, 'output_verification_meta'], 0);
        add_action('wp_head', [$self, 'output_banner_snippet'], 0);
    }

    public static function activate(): void
    {
        // Ensure the service URL is set even if the UI doesn't expose it.
        $self = new self();
        $existing = (string) get_option(self::OPT_SERVICE_URL, '');
        $normalizedExisting = $self->normalize_base_url($existing);
        if ($existing !== '' && $normalizedExisting !== '') {
            if ($normalizedExisting !== rtrim(trim($existing), "/ \t\n\r\0\x0B")) {
                update_option(self::OPT_SERVICE_URL, $normalizedExisting);
            }
            if (get_option(self::OPT_AUTO_SYNC, null) === null) {
                update_option(self::OPT_AUTO_SYNC, '1');
            }
            return;
        }

        $default = self::DEFAULT_SERVICE_URL;
        if (defined('SC_SERVICE_URL')) {
            $maybe = (string) constant('SC_SERVICE_URL');
            if (trim($maybe) !== '') {
                $default = $maybe;
            }
        } else {
            $filtered = apply_filters('sc_default_service_url', $default);
            if (is_string($filtered) && trim($filtered) !== '') {
                $default = $filtered;
            }
        }

        update_option(self::OPT_SERVICE_URL, $self->normalize_base_url((string) $default));
        if (get_option(self::OPT_AUTO_SYNC, null) === null) {
            update_option(self::OPT_AUTO_SYNC, '1');
        }
    }

    public function admin_menu(): void
    {
        add_menu_page(
            esc_html__('TruCookie CMP', 'trucookie-cmp-consent-mode-v2'),
            esc_html__('TruCookie', 'trucookie-cmp-consent-mode-v2'),
            'manage_options',
            'trucookie-cmp-consent-mode-v2',
            [$this, 'render_settings_page'],
            'dashicons-shield',
            59
        );
    }

}

register_activation_hook(__FILE__, [SaaS_Cookie_CMP_Plugin::class, 'activate']);
SaaS_Cookie_CMP_Plugin::init();



