<?php

namespace TruCookieCMP\Admin;

use TruCookieCMP\Core\PlanSync;
use TruCookieCMP\Core\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class Admin
{
    private const NOTICE_TRANSIENT_KEY = 'tcs_admin_notice';

    /** @var Settings */
    private $settings;

    /** @var PlanSync */
    private $planSync;

    public function __construct(Settings $settings, PlanSync $planSync)
    {
        $this->settings = $settings;
        $this->planSync = $planSync;
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'maybe_export_logs']);
        add_action('admin_init', [$this, 'maybe_show_persisted_notice']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('update_option_' . Settings::OPTION_KEY, [$this, 'after_settings_updated'], 10, 3);
    }

    public function enqueue_assets(string $hookSuffix): void
    {
        if ($hookSuffix !== 'toplevel_page_trucookie-cmp-consent-mode-v2') {
            return;
        }

        wp_enqueue_style(
            'tcs-cmp-admin-style',
            TRUCOOKIE_CMP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            TRUCOOKIE_CMP_VERSION
        );
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('TruCookie CMP', 'trucookie-cmp-consent-mode-v2'),
            __('TruCookie CMP', 'trucookie-cmp-consent-mode-v2'),
            'manage_options', 'trucookie-cmp-consent-mode-v2',
            [$this, 'render_page'],
            'dashicons-shield',
            58
        );
    }

    public function register_settings(): void
    {
        register_setting(
            'tcs_settings_group',
            Settings::OPTION_KEY,
            [$this, 'sanitize_settings']
        );
    }

    /**
     * @param mixed $input
     * @return array<string,string>
     */
    public function sanitize_settings($input): array
    {
        $sanitized = $this->settings->sanitize_input($input);
        $sanitized = $this->merge_managed_state($sanitized);
        $sanitized = $this->sync_remote_site_registration($sanitized);
        $enforced = $this->planSync->enforce_settings($sanitized);
        if (!empty($enforced['changed'])) {
            add_settings_error(
                'tcs_settings_group',
                'tcs_plan_restrictions',
                __('Some premium-only settings were reset to match your current plan.', 'trucookie-cmp-consent-mode-v2'),
                'warning'
            );
        }

        return isset($enforced['settings']) && is_array($enforced['settings']) ? $enforced['settings'] : $sanitized;
    }

    public function after_settings_updated($oldValue, $value, $option): void
    {
        if ($option !== Settings::OPTION_KEY || !is_array($value)) {
            return;
        }

        $settings = $this->merge_managed_state($value);
        $sitePublicId = trim((string) ($settings['site_public_id'] ?? ''));
        if ($sitePublicId === '' || trim((string) ($settings['verification_token'] ?? '')) === '') {
            return;
        }

        $response = $this->plugin_api_request(
            $settings,
            'POST',
            '/api/plugin/sites/' . rawurlencode($sitePublicId) . '/verify'
        );

        if (is_wp_error($response)) {
            $this->persist_admin_notice(
                'warning',
                sprintf(
                    __('TruCookie site verification could not be completed automatically: %s', 'trucookie-cmp-consent-mode-v2'),
                    $response->get_error_message()
                )
            );
            return;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status >= 200 && $status < 300) {
            $this->persist_admin_notice(
                'success',
                __('TruCookie site connection is active and the website verification check passed.', 'trucookie-cmp-consent-mode-v2')
            );
            return;
        }

        $message = $this->extract_api_error_message($response);
        $this->persist_admin_notice(
            'warning',
            sprintf(
                __('TruCookie site was synced, but website verification still needs attention: %s', 'trucookie-cmp-consent-mode-v2'),
                $message
            )
        );
    }

    public function maybe_show_persisted_notice(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $notice = get_transient(self::NOTICE_TRANSIENT_KEY);
        if (!is_array($notice)) {
            return;
        }

        delete_transient(self::NOTICE_TRANSIENT_KEY);
        $type = isset($notice['type']) ? sanitize_key((string) $notice['type']) : 'info';
        $message = isset($notice['message']) ? (string) $notice['message'] : '';
        if ($message === '') {
            return;
        }

        add_settings_error('tcs_settings_group', 'tcs_connection_notice', $message, $type);
    }

    public function maybe_export_logs(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $page = (string) filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS);
        $page = sanitize_key(wp_unslash($page));
        if ($page !== 'trucookie-cmp-consent-mode-v2') {
            return;
        }

        $export = (string) filter_input(INPUT_GET, 'tcs_export_logs', FILTER_SANITIZE_SPECIAL_CHARS);
        $export = sanitize_key(wp_unslash($export));
        if ($export !== '1') {
            return;
        }

        $nonce = (string) filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_SPECIAL_CHARS);
        $nonce = sanitize_text_field(wp_unslash($nonce));
        if (!wp_verify_nonce($nonce, 'tcs_export_logs')) {
            wp_die(esc_html__('Invalid export nonce.', 'trucookie-cmp-consent-mode-v2'));
        }

        $logs = get_option(Settings::LOG_OPTION_KEY, []);
        if (!is_array($logs)) {
            $logs = [];
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="trucookie-consent-logs.csv"');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            exit;
        }

        fputcsv($output, ['created_at', 'site_url', 'analytics', 'marketing', 'url', 'referrer', 'source', 'plugin_version']);

        foreach ($logs as $row) {
            $consent = isset($row['consent']) && is_array($row['consent']) ? $row['consent'] : [];
            fputcsv($output, [
                (string) ($row['created_at'] ?? ''),
                (string) ($row['site_url'] ?? $row['site_public_id'] ?? ''),
                !empty($consent['analytics']) ? '1' : '0',
                !empty($consent['marketing']) ? '1' : '0',
                (string) ($row['url'] ?? ''),
                (string) ($row['referrer'] ?? ''),
                (string) ($row['source'] ?? ''),
                (string) ($row['plugin_version'] ?? ''),
            ]);
        }

        exit;
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $s = $this->settings->all();
        $logs = get_option(Settings::LOG_OPTION_KEY, []);
        if (!is_array($logs)) {
            $logs = [];
        }
        $logs = array_slice($logs, 0, 10);

        $onboarding = (string) filter_input(INPUT_GET, 'onboarding', FILTER_SANITIZE_SPECIAL_CHARS);
        $onboarding = sanitize_key(wp_unslash($onboarding)) === '1';
        $exportUrl = wp_nonce_url(admin_url('admin.php?page=trucookie-cmp-consent-mode-v2&tcs_export_logs=1'), 'tcs_export_logs');
        $scriptBlockerSnippet = 'type="text/plain" data-tc-category="analytics" src="..."';

        $bannerStatus = $s['banner_enabled'] === '1' ? 'Active' : 'Inactive';
        $regulationLabel = $s['regulation'] === 'us' ? 'US State Laws' : 'GDPR';
        $languageMap = ['auto' => 'Auto', 'en' => 'English', 'pl' => 'Polski'];
        $languageMap['de'] = 'Deutsch';
        $geoMap = ['worldwide' => 'Worldwide', 'eu-uk' => 'EU Countries & UK'];
        $modeMap = ['auto' => 'Auto', 'local' => 'Local', 'connected' => 'Connected'];
        $schemeMap = ['auto' => 'Auto', 'light' => 'White', 'dark' => 'Dark'];
        $planSnapshot = $this->planSync->resolve($s);
        $planName = (string) ($planSnapshot['name'] ?? 'free');
        if ($planName === '') {
            $planName = 'free';
        }
        $planLabelMap = [
            'free' => __('Free', 'trucookie-cmp-consent-mode-v2'),
            'starter' => __('Starter', 'trucookie-cmp-consent-mode-v2'),
            'agency' => __('Agency', 'trucookie-cmp-consent-mode-v2'),
        ];
        $planLabel = $planLabelMap[$planName] ?? ucwords(str_replace('_', ' ', $planName));
        $sitesUsage = (int) ($planSnapshot['usage']['sites'] ?? 0);
        $sitesLimit = (int) ($planSnapshot['limits']['sites'] ?? 1);
        $scansUsage = (int) ($planSnapshot['usage']['scans_this_month'] ?? 0);
        $scansLimit = (int) ($planSnapshot['limits']['scans_per_month'] ?? 5);
        $canRunDeepAudit = !empty($planSnapshot['can_run_deep_audit']);
        $isPremiumPlan = $planName !== 'free';
        $planSyncSource = (string) ($planSnapshot['source'] ?? 'local');
        $planSyncMessage = (string) ($planSnapshot['message'] ?? '');
        $planSyncedAtRaw = (string) ($planSnapshot['synced_at'] ?? '');
        $planSyncLabelMap = [
            'remote' => __('Connected', 'trucookie-cmp-consent-mode-v2'),
            'local' => __('Local (no API sync)', 'trucookie-cmp-consent-mode-v2'),
            'error' => __('Sync error', 'trucookie-cmp-consent-mode-v2'),
        ];
        $planSyncLabel = $planSyncLabelMap[$planSyncSource] ?? __('Unknown', 'trucookie-cmp-consent-mode-v2');
        $planSyncedAt = '';
        if ($planSyncedAtRaw !== '') {
            $timestamp = strtotime($planSyncedAtRaw);
            if ($timestamp !== false) {
                $planSyncedAt = wp_date('Y-m-d H:i', $timestamp);
            }
        }
        $premiumLabel = __('Premium', 'trucookie-cmp-consent-mode-v2');
        ?>
        <div class="wrap tcs-admin">
            <h1><?php echo esc_html__('TruCookie CMP Stable', 'trucookie-cmp-consent-mode-v2'); ?></h1>
            <p><?php echo esc_html__('WordPress cookie banner with local runtime, wp-consent-api bridge, Google Consent Mode, and optional connected remote renderer.', 'trucookie-cmp-consent-mode-v2'); ?></p>

            <?php if ($onboarding) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?php echo esc_html__('Setup complete.', 'trucookie-cmp-consent-mode-v2'); ?></strong></p>
                    <p><?php echo esc_html__('Enable banner, choose regulation/language/theme, save, then test in incognito with ?tcs_reset_consent=1&tcs_force_banner=1.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                </div>
            <?php endif; ?>
            <?php settings_errors('tcs_settings_group'); ?>

            <div class="tcs-card tcs-overview-wrap">
                <h2><?php echo esc_html__('Overview', 'trucookie-cmp-consent-mode-v2'); ?></h2>
                <div class="tcs-overview-grid">
                    <div class="tcs-overview-card">
                        <div class="tcs-overview-label"><?php echo esc_html__('Current plan', 'trucookie-cmp-consent-mode-v2'); ?></div>
                        <div class="tcs-overview-value">
                            <?php echo esc_html($planLabel); ?>
                        </div>
                        <div class="tcs-overview-label">
                            <?php
                            /* translators: 1: used sites, 2: site limit */
                            echo esc_html(sprintf(__('Sites: %1$d/%2$d', 'trucookie-cmp-consent-mode-v2'), $sitesUsage, $sitesLimit));
                            ?>
                        </div>
                        <div class="tcs-overview-label">
                            <?php
                            /* translators: 1: used scans this month, 2: monthly scans limit */
                            echo esc_html(sprintf(__('Audits: %1$d/%2$d', 'trucookie-cmp-consent-mode-v2'), $scansUsage, $scansLimit));
                            ?>
                        </div>
                    </div>
                    <div class="tcs-overview-card">
                        <div class="tcs-overview-label"><?php echo esc_html__('Banner status', 'trucookie-cmp-consent-mode-v2'); ?></div>
                        <div class="tcs-overview-value"><?php echo esc_html($bannerStatus); ?></div>
                    </div>
                    <div class="tcs-overview-card">
                        <div class="tcs-overview-label"><?php echo esc_html__('Regulation', 'trucookie-cmp-consent-mode-v2'); ?></div>
                        <div class="tcs-overview-value"><?php echo esc_html($regulationLabel); ?></div>
                    </div>
                    <div class="tcs-overview-card">
                        <div class="tcs-overview-label"><?php echo esc_html__('Language', 'trucookie-cmp-consent-mode-v2'); ?></div>
                        <div class="tcs-overview-value"><?php echo esc_html($languageMap[$s['banner_language']] ?? 'Auto'); ?></div>
                    </div>
                    <div class="tcs-overview-card">
                        <div class="tcs-overview-label"><?php echo esc_html__('Geo-target banner', 'trucookie-cmp-consent-mode-v2'); ?></div>
                        <div class="tcs-overview-value"><?php echo esc_html($geoMap[$s['geo_target']] ?? 'Worldwide'); ?></div>
                    </div>
                    <div class="tcs-overview-card">
                        <div class="tcs-overview-label"><?php echo esc_html__('Renderer mode', 'trucookie-cmp-consent-mode-v2'); ?></div>
                        <div class="tcs-overview-value"><?php echo esc_html($modeMap[$s['mode']] ?? 'Auto'); ?></div>
                    </div>
                    <div class="tcs-overview-card">
                        <div class="tcs-overview-label"><?php echo esc_html__('Theme mode', 'trucookie-cmp-consent-mode-v2'); ?></div>
                        <div class="tcs-overview-value"><?php echo esc_html($schemeMap[$s['color_scheme']] ?? 'Auto'); ?></div>
                    </div>
                    <div class="tcs-overview-card">
                        <div class="tcs-overview-label"><?php echo esc_html__('Plan sync', 'trucookie-cmp-consent-mode-v2'); ?></div>
                        <div class="tcs-overview-value"><?php echo esc_html($planSyncLabel); ?></div>
                        <?php if ($planSyncedAt !== '') : ?>
                            <div class="tcs-overview-label">
                                <?php
                                /* translators: %s: datetime */
                                echo esc_html(sprintf(__('Updated: %s', 'trucookie-cmp-consent-mode-v2'), $planSyncedAt));
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="tcs-quick-actions">
                    <a class="button button-secondary" href="https://trucookie.pro/login" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Log in to trucookie.pro', 'trucookie-cmp-consent-mode-v2'); ?></a>
                    <a class="button button-primary" href="https://trucookie.pro/register" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Create account in trucookie.pro', 'trucookie-cmp-consent-mode-v2'); ?></a>
                </div>
            </div>

            <div class="tcs-card">
                <h2><?php echo esc_html__('Support and SLA', 'trucookie-cmp-consent-mode-v2'); ?></h2>
                <p>
                    <?php
                    echo esc_html__(
                        'For Consent Mode matters, contact our support first.',
                        'trucookie-cmp-consent-mode-v2'
                    );
                    ?>
                </p>
                <p>
                    <?php echo esc_html__('Support commitment: initial response within 4 business days.', 'trucookie-cmp-consent-mode-v2'); ?>
                </p>
                <div class="tcs-quick-actions">
                    <a class="button button-primary" href="https://trucookie.pro/contact-support" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Open support center', 'trucookie-cmp-consent-mode-v2'); ?></a>
                    <a class="button button-secondary" href="https://trucookie.pro/contact-support#contact-form" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Go to contact section', 'trucookie-cmp-consent-mode-v2'); ?></a>
                </div>
            </div>

            <div class="notice notice-info tcs-plan-note">
                <p>
                    <?php
                    echo esc_html__(
                        'Plan data is synced from TruCookie API when Service URL and API key are configured. Current limits and usage are shown in Overview.',
                        'trucookie-cmp-consent-mode-v2'
                    );
                    ?>
                </p>
                <?php if ($planSyncMessage !== '') : ?>
                    <p><?php echo esc_html($planSyncMessage); ?></p>
                <?php endif; ?>
                <p>
                    <?php echo esc_html($canRunDeepAudit ? __('Deep audit: enabled on your current plan.', 'trucookie-cmp-consent-mode-v2') : __('Deep audit: unavailable on your current plan.', 'trucookie-cmp-consent-mode-v2')); ?>
                    <span class="tcs-premium-badge" aria-label="<?php echo esc_attr($premiumLabel); ?>">
                        <span class="tcs-premium-star" aria-hidden="true"></span>
                        <?php echo esc_html($premiumLabel); ?>
                    </span>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('tcs_settings_group'); ?>

                <div class="tcs-card">
                <h2><?php echo esc_html__('Banner setup', 'trucookie-cmp-consent-mode-v2'); ?></h2>
                <table class="form-table tcs-form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Banner enabled', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[banner_enabled]" value="1" <?php checked($s['banner_enabled'], '1'); ?>>
                                <?php echo esc_html__('Show banner on frontend', 'trucookie-cmp-consent-mode-v2'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Regulation', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[regulation]">
                                <option value="gdpr" <?php selected($s['regulation'], 'gdpr'); ?>><?php echo esc_html__('GDPR', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="us" <?php selected($s['regulation'], 'us'); ?>><?php echo esc_html__('US State Laws', 'trucookie-cmp-consent-mode-v2'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Language', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[banner_language]">
                                <option value="auto" <?php selected($s['banner_language'], 'auto'); ?>><?php echo esc_html__('Auto', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="en" <?php selected($s['banner_language'], 'en'); ?>><?php echo esc_html__('English', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="pl" <?php selected($s['banner_language'], 'pl'); ?>><?php echo esc_html__('Polski', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="de" <?php selected($s['banner_language'], 'de'); ?>><?php echo esc_html__('Deutsch', 'trucookie-cmp-consent-mode-v2'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Geo-target banner', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[geo_target]">
                                <option value="worldwide" <?php selected($s['geo_target'], 'worldwide'); ?>><?php echo esc_html__('Worldwide', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="eu-uk" <?php selected($s['geo_target'], 'eu-uk'); ?>><?php echo esc_html__('EU Countries & UK', 'trucookie-cmp-consent-mode-v2'); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html__('EU/UK mode uses server country headers when available (e.g. Cloudflare). If country cannot be detected, banner is shown by default.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Mode', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[mode]">
                                <option value="auto" <?php selected($s['mode'], 'auto'); ?>><?php echo esc_html__('Auto', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="local" <?php selected($s['mode'], 'local'); ?>><?php echo esc_html__('Local only', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="connected" <?php selected($s['mode'], 'connected'); ?> <?php disabled(!$isPremiumPlan); ?>><?php echo esc_html__('Connected (remote first) [Premium]', 'trucookie-cmp-consent-mode-v2'); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html__('Connected mode requires "Remote banner URL". Without it plugin falls back to local renderer.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Banner style', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[style]">
                                <option value="bar" <?php selected($s['style'], 'bar'); ?>><?php echo esc_html__('Bar (bottom centered)', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="rectangle-left" <?php selected($s['style'], 'rectangle-left'); ?>><?php echo esc_html__('Rectangle left', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="rectangle-right" <?php selected($s['style'], 'rectangle-right'); ?>><?php echo esc_html__('Rectangle right', 'trucookie-cmp-consent-mode-v2'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Color scheme', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[color_scheme]">
                                <option value="auto" <?php selected($s['color_scheme'], 'auto'); ?>><?php echo esc_html__('Auto', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="light" <?php selected($s['color_scheme'], 'light'); ?>><?php echo esc_html__('White', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                <option value="dark" <?php selected($s['color_scheme'], 'dark'); ?>><?php echo esc_html__('Dark', 'trucookie-cmp-consent-mode-v2'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Consent expiry (days)', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <input type="number" min="1" max="3650" step="1" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[consent_expiry_days]" value="<?php echo esc_attr($s['consent_expiry_days']); ?>">
                        </td>
                    </tr>
                </table>
                </div>

                <div class="tcs-card">
                <h2><?php echo esc_html__('Banner UX', 'trucookie-cmp-consent-mode-v2'); ?></h2>
                <table class="form-table tcs-form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Buttons', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[show_decline_button]" value="1" <?php checked($s['show_decline_button'], '1'); ?>>
                                <?php echo esc_html__('Show decline button', 'trucookie-cmp-consent-mode-v2'); ?>
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[show_preferences_button]" value="1" <?php checked($s['show_preferences_button'], '1'); ?>>
                                <?php echo esc_html__('Show preferences button', 'trucookie-cmp-consent-mode-v2'); ?>
                            </label>
                            <label style="display:block;">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[show_powered_by]" value="1" <?php checked($s['show_powered_by'], '1'); ?>>
                                <?php echo esc_html__('Show TruCookie brand footer', 'trucookie-cmp-consent-mode-v2'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Revisit consent button', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[show_revisit_button]" value="1" <?php checked($s['show_revisit_button'], '1'); ?>>
                                <?php echo esc_html__('Display floating revisit button after consent', 'trucookie-cmp-consent-mode-v2'); ?>
                            </label>
                            <input class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[revisit_button_text]" value="<?php echo esc_attr($s['revisit_button_text']); ?>">
                        </td>
                    </tr>
                </table>
                </div>

                <div class="tcs-card">
                <h2><?php echo esc_html__('Compliance & Integrations', 'trucookie-cmp-consent-mode-v2'); ?></h2>
                <table class="form-table tcs-form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Google Consent Mode v2', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[gcm_enabled]" value="1" <?php checked($s['gcm_enabled'], '1'); ?>>
                                <?php echo esc_html__('Enable GCM (default denied, update on consent)', 'trucookie-cmp-consent-mode-v2'); ?>
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <?php echo esc_html__('Runtime mode', 'trucookie-cmp-consent-mode-v2'); ?>
                                <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[gcm_mode]">
                                    <option value="advanced" <?php selected($s['gcm_mode'], 'advanced'); ?>><?php echo esc_html__('Advanced (default/update enabled)', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                    <option value="basic" <?php selected($s['gcm_mode'], 'basic'); ?>><?php echo esc_html__('Basic (block first)', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                </select>
                            </label>
                            <p class="description" style="margin-top:0;margin-bottom:8px;">
                                <?php echo esc_html__('Recommended (Consent Mode without TCF): use the default TruCookie banner template text and policy links.', 'trucookie-cmp-consent-mode-v2'); ?>
                            </p>
                            <label style="display:block;margin-bottom:6px;">
                                <?php echo esc_html__('Google developer ID (optional)', 'trucookie-cmp-consent-mode-v2'); ?>
                                <input class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[gcm_developer_id]" value="<?php echo esc_attr($s['gcm_developer_id']); ?>" placeholder="dXXXXXXX">
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <?php echo esc_html__('Default consent (Global)', 'trucookie-cmp-consent-mode-v2'); ?>
                                <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[gcm_default_global]">
                                    <option value="denied" <?php selected($s['gcm_default_global'], 'denied'); ?>><?php echo esc_html__('Denied', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                    <option value="granted" <?php selected($s['gcm_default_global'], 'granted'); ?>><?php echo esc_html__('Granted', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                </select>
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <?php echo esc_html__('Default consent (EEA/UK/CH)', 'trucookie-cmp-consent-mode-v2'); ?>
                                <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[gcm_default_eea]">
                                    <option value="denied" <?php selected($s['gcm_default_eea'], 'denied'); ?>><?php echo esc_html__('Denied', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                    <option value="granted" <?php selected($s['gcm_default_eea'], 'granted'); ?>><?php echo esc_html__('Granted', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                </select>
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <?php echo esc_html__('Default consent (US)', 'trucookie-cmp-consent-mode-v2'); ?>
                                <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[gcm_default_us]">
                                    <option value="denied" <?php selected($s['gcm_default_us'], 'denied'); ?>><?php echo esc_html__('Denied', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                    <option value="granted" <?php selected($s['gcm_default_us'], 'granted'); ?>><?php echo esc_html__('Granted', 'trucookie-cmp-consent-mode-v2'); ?></option>
                                </select>
                            </label>
                            <label>
                                <?php echo esc_html__('wait_for_update (ms)', 'trucookie-cmp-consent-mode-v2'); ?>
                                <input type="number" min="0" max="5000" step="100" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[gcm_wait_for_update]" value="<?php echo esc_attr($s['gcm_wait_for_update']); ?>">
                            </label>
                            <p class="description"><?php echo esc_html__('Basic mode disables Consent Mode default/update commands and relies on blocking until consent.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Script blocker', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[enable_script_blocker]" value="1" <?php checked($s['enable_script_blocker'], '1'); ?>>
                                <?php echo esc_html__('Enable deterministic script unlocker by category', 'trucookie-cmp-consent-mode-v2'); ?>
                            </label>
                            <p class="description">
                                <?php
                                echo esc_html__('Use:', 'trucookie-cmp-consent-mode-v2') . ' ' . esc_html($scriptBlockerSnippet);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Respect DNT', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[respect_dnt]" value="1" <?php checked($s['respect_dnt'], '1'); ?>>
                                <?php echo esc_html__('When browser sends Do Not Track=1, set essential-only consent automatically.', 'trucookie-cmp-consent-mode-v2'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Consent log compliance', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[collect_user_metadata]" value="1" <?php checked($s['collect_user_metadata'], '1'); ?>>
                                <?php echo esc_html__('Collect technical metadata in local logs (IP hint and user agent).', 'trucookie-cmp-consent-mode-v2'); ?>
                            </label>
                            <label style="display:block;">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[forward_consent_logs]" value="1" <?php checked($s['forward_consent_logs'], '1'); ?> <?php disabled(!$isPremiumPlan); ?>>
                                <?php echo esc_html__('Allow forwarding consent events to configured TruCookie endpoint.', 'trucookie-cmp-consent-mode-v2'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Enable these only if your privacy policy clearly discloses collection/transfer and your legal basis is in place.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                            <?php if (!$isPremiumPlan) : ?>
                                <p class="description"><?php echo esc_html__('Consent forwarding is available on Premium plans.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                </div>

                <div class="tcs-card">
                <h2>
                    <?php echo esc_html__('TruCookie.pro connection', 'trucookie-cmp-consent-mode-v2'); ?>
                    <span class="tcs-premium-badge" aria-label="<?php echo esc_attr($premiumLabel); ?>">
                        <span class="tcs-premium-star" aria-hidden="true"></span>
                        <?php echo esc_html($premiumLabel); ?>
                    </span>
                </h2>
                <table class="form-table tcs-form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Service URL', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <input class="regular-text" type="url" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[service_url]" value="<?php echo esc_attr($s['service_url']); ?>">
                            <p class="description"><?php echo esc_html__('Base URL of TruCookie service (for API calls).', 'trucookie-cmp-consent-mode-v2'); ?></p>
                            <?php if (!empty($s['site_public_id'])) : ?>
                                <p class="description">
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            __('Connected site ID: %s', 'trucookie-cmp-consent-mode-v2'),
                                            (string) $s['site_public_id']
                                        )
                                    );
                                    ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('API key', 'trucookie-cmp-consent-mode-v2'); ?>
                            <span class="tcs-premium-badge tcs-premium-badge-inline" aria-label="<?php echo esc_attr($premiumLabel); ?>">
                                <span class="tcs-premium-star" aria-hidden="true"></span>
                                <?php echo esc_html($premiumLabel); ?>
                            </span>
                        </th>
                        <td>
                            <input class="regular-text" type="password" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[api_key]" value="<?php echo esc_attr($s['api_key']); ?>" autocomplete="off">
                            <p class="description"><?php echo esc_html__('Generate API key in trucookie.pro and paste it here. This key is used for consent logs API forwarding.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                            <div class="tcs-quick-actions" style="margin-top:8px;">
                                <a class="button button-secondary" href="https://trucookie.pro/login" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Log in', 'trucookie-cmp-consent-mode-v2'); ?></a>
                                <a class="button button-primary" href="https://trucookie.pro/register" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Create account', 'trucookie-cmp-consent-mode-v2'); ?></a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Remote banner URL (optional)', 'trucookie-cmp-consent-mode-v2'); ?>
                            <span class="tcs-premium-badge tcs-premium-badge-inline" aria-label="<?php echo esc_attr($premiumLabel); ?>">
                                <span class="tcs-premium-star" aria-hidden="true"></span>
                                <?php echo esc_html($premiumLabel); ?>
                            </span>
                        </th>
                        <td>
                            <input class="regular-text" type="url" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[remote_banner_url]" value="<?php echo esc_attr($s['remote_banner_url']); ?>" <?php disabled(!$isPremiumPlan); ?>>
                            <p class="description"><?php echo esc_html__('If set, connected mode will try this URL first. If remote script fails, plugin falls back to local banner automatically.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                            <?php if (!$isPremiumPlan) : ?>
                                <p class="description"><?php echo esc_html__('Remote banner URL is available on Premium plans.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Consent log path', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <input class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[log_path]" value="<?php echo esc_attr($s['log_path']); ?>">
                            <p class="description"><?php echo esc_html__('Path appended to Service URL for server-side consent logging.', 'trucookie-cmp-consent-mode-v2'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Remote timeout (ms)', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <input type="number" min="1000" max="10000" step="100" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[remote_timeout_ms]" value="<?php echo esc_attr($s['remote_timeout_ms']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Debug mode', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[debug]" value="1" <?php checked($s['debug'], '1'); ?>>
                                <?php echo esc_html__('Expose diagnostics and include remote response body in REST result.', 'trucookie-cmp-consent-mode-v2'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                </div>

                <?php submit_button(__('Save settings', 'trucookie-cmp-consent-mode-v2')); ?>
            </form>

            <div class="tcs-card">
                <h2><?php echo esc_html__('Diagnostics', 'trucookie-cmp-consent-mode-v2'); ?></h2>
                <p><code>window.trucookieCmp && window.trucookieCmp.diagnostics()</code></p>
                <p><code>window.scCmp && window.scCmp.openSettings()</code></p>
                <p><code><?php echo esc_html(rest_url('trucookie-cmp/v1/consent')); ?></code></p>
                <p class="tcs-help-note"><?php echo esc_html__('wp-consent-api integration is enabled. Categories are synced through wp_set_consent when consent is saved.', 'trucookie-cmp-consent-mode-v2'); ?></p>
            </div>

            <div class="tcs-card">
                <h2><?php echo esc_html__('Consent logs (local)', 'trucookie-cmp-consent-mode-v2'); ?></h2>
                <p><a class="button" href="<?php echo esc_url($exportUrl); ?>"><?php echo esc_html__('Export CSV', 'trucookie-cmp-consent-mode-v2'); ?></a></p>

            <?php if (empty($logs)) : ?>
                <p><?php echo esc_html__('No logs yet.', 'trucookie-cmp-consent-mode-v2'); ?></p>
            <?php else : ?>
                <table class="widefat striped tcs-logs-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Time', 'trucookie-cmp-consent-mode-v2'); ?></th>
                            <th><?php echo esc_html__('Site URL', 'trucookie-cmp-consent-mode-v2'); ?></th>
                            <th><?php echo esc_html__('Analytics', 'trucookie-cmp-consent-mode-v2'); ?></th>
                            <th><?php echo esc_html__('Marketing', 'trucookie-cmp-consent-mode-v2'); ?></th>
                            <th><?php echo esc_html__('URL', 'trucookie-cmp-consent-mode-v2'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $row) : ?>
                            <?php
                            $consent = isset($row['consent']) && is_array($row['consent']) ? $row['consent'] : [];
                            $analytics = !empty($consent['analytics']) ? '1' : '0';
                            $marketing = !empty($consent['marketing']) ? '1' : '0';
                            ?>
                            <tr>
                                <td><?php echo esc_html((string) ($row['created_at'] ?? '-')); ?></td>
                                <td><?php echo esc_html((string) ($row['site_url'] ?? $row['site_public_id'] ?? '-')); ?></td>
                                <td><code><?php echo esc_html($analytics); ?></code></td>
                                <td><code><?php echo esc_html($marketing); ?></code></td>
                                <td><?php echo esc_html((string) ($row['url'] ?? '-')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string,string> $settings
     * @return array<string,string>
     */
    private function merge_managed_state(array $settings): array
    {
        $current = $this->settings->all();
        foreach (['site_public_id', 'verification_token'] as $key) {
            if (!isset($settings[$key]) && isset($current[$key])) {
                $settings[$key] = (string) $current[$key];
            }
        }

        return $settings;
    }

    /**
     * @param array<string,string> $settings
     * @return array<string,string>
     */
    private function sync_remote_site_registration(array $settings): array
    {
        if (
            trim((string) ($settings['service_url'] ?? '')) === ''
            || trim((string) ($settings['api_key'] ?? '')) === ''
        ) {
            return $settings;
        }

        $response = $this->plugin_api_request($settings, 'POST', '/api/plugin/sites/ensure', [
            'url' => (string) home_url('/'),
            'default_locale' => $this->resolve_wordpress_locale(),
        ]);

        if (is_wp_error($response)) {
            add_settings_error(
                'tcs_settings_group',
                'tcs_remote_site_sync_failed',
                sprintf(
                    __('Could not sync this WordPress site with TruCookie: %s', 'trucookie-cmp-consent-mode-v2'),
                    $response->get_error_message()
                ),
                'warning'
            );
            return $settings;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300 || !is_array($body)) {
            add_settings_error(
                'tcs_settings_group',
                'tcs_remote_site_sync_failed',
                sprintf(
                    __('Could not sync this WordPress site with TruCookie: %s', 'trucookie-cmp-consent-mode-v2'),
                    $this->extract_api_error_message($response)
                ),
                'warning'
            );
            return $settings;
        }

        $site = is_array($body['site'] ?? null) ? $body['site'] : [];
        $integrations = is_array($body['integrations'] ?? null) ? $body['integrations'] : [];

        $sitePublicId = trim((string) ($site['public_id'] ?? ''));
        $verificationToken = trim((string) ($site['verification_token'] ?? ''));
        $bannerScriptSrc = trim((string) ($integrations['banner_script_src'] ?? ''));

        if ($sitePublicId !== '') {
            $settings['site_public_id'] = $sitePublicId;
        }
        if ($verificationToken !== '') {
            $settings['verification_token'] = $verificationToken;
        }
        if (trim((string) ($settings['remote_banner_url'] ?? '')) === '' && $bannerScriptSrc !== '') {
            $settings['remote_banner_url'] = $bannerScriptSrc;
        }

        add_settings_error(
            'tcs_settings_group',
            'tcs_remote_site_sync_ok',
            __('This WordPress site is now linked with TruCookie.', 'trucookie-cmp-consent-mode-v2'),
            'success'
        );

        return $settings;
    }

    /**
     * @param array<string,string> $settings
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>|\WP_Error
     */
    private function plugin_api_request(array $settings, string $method, string $path, ?array $body = null)
    {
        $serviceUrl = rtrim((string) ($settings['service_url'] ?? ''), '/');
        $apiKey = trim((string) ($settings['api_key'] ?? ''));
        if ($serviceUrl === '' || $apiKey === '') {
            return new \WP_Error('tcs_missing_credentials', 'Missing TruCookie service credentials.');
        }

        $url = $serviceUrl . $path;
        $args = [
            'timeout' => 8,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json; charset=utf-8',
            ],
        ];

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        if (strtoupper($method) === 'GET') {
            return wp_remote_get($url, $args);
        }

        return wp_remote_post($url, $args);
    }

    /**
     * @param array<string,mixed>|\WP_Error $response
     */
    private function extract_api_error_message($response): string
    {
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (is_array($body) && is_string($body['message'] ?? null) && trim((string) $body['message']) !== '') {
            return (string) $body['message'];
        }
        if (is_array($body) && is_string($body['error'] ?? null) && trim((string) $body['error']) !== '') {
            return (string) $body['error'];
        }

        return 'HTTP ' . (int) wp_remote_retrieve_response_code($response);
    }

    private function persist_admin_notice(string $type, string $message): void
    {
        set_transient(self::NOTICE_TRANSIENT_KEY, [
            'type' => $type,
            'message' => $message,
        ], 120);
    }

    private function resolve_wordpress_locale(): string
    {
        $locale = function_exists('determine_locale') ? (string) determine_locale() : (string) get_locale();
        $locale = strtolower($locale);
        if (strpos($locale, 'pl') === 0) {
            return 'pl';
        }
        if (strpos($locale, 'de') === 0) {
            return 'de';
        }

        return 'en';
    }
}
