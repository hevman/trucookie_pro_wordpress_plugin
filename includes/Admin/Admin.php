<?php

namespace TruCookieCMP\Admin;

use TruCookieCMP\Core\Settings;

final class Admin
{
    /** @var Settings */
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'maybe_export_logs']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(string $hookSuffix): void
    {
        if ($hookSuffix !== 'toplevel_page_trucookie-cmp-stable') {
            return;
        }

        wp_enqueue_style(
            'tcs-cmp-admin-style',
            TCS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            TCS_VERSION
        );
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('TruCookie CMP', 'trucookie-cmp-stable'),
            __('TruCookie CMP', 'trucookie-cmp-stable'),
            'manage_options',
            'trucookie-cmp-stable',
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
        return $this->settings->sanitize_input($input);
    }

    public function maybe_export_logs(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if ($page !== 'trucookie-cmp-stable') {
            return;
        }

        $export = isset($_GET['tcs_export_logs']) ? sanitize_key((string) $_GET['tcs_export_logs']) : '';
        if ($export !== '1') {
            return;
        }

        $nonce = isset($_GET['_wpnonce']) ? (string) $_GET['_wpnonce'] : '';
        if (!wp_verify_nonce($nonce, 'tcs_export_logs')) {
            wp_die(esc_html__('Invalid export nonce.', 'trucookie-cmp-stable'));
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

        fclose($output);
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

        $onboarding = isset($_GET['onboarding']) && (string) $_GET['onboarding'] === '1';
        $exportUrl = wp_nonce_url(admin_url('admin.php?page=trucookie-cmp-stable&tcs_export_logs=1'), 'tcs_export_logs');

        $bannerStatus = $s['banner_enabled'] === '1' ? 'Active' : 'Inactive';
        $regulationLabel = $s['regulation'] === 'us' ? 'US State Laws' : 'GDPR';
        $languageMap = ['auto' => 'Auto', 'en' => 'English', 'pl' => 'Polski'];
        $geoMap = ['worldwide' => 'Worldwide', 'eu-uk' => 'EU Countries & UK'];
        $modeMap = ['auto' => 'Auto', 'local' => 'Local', 'connected' => 'Connected'];
        $schemeMap = ['auto' => 'Auto', 'light' => 'White', 'dark' => 'Dark'];
        ?>
        <div class="wrap tcs-admin">
            <h1><?php echo esc_html__('TruCookie CMP Stable', 'trucookie-cmp-stable'); ?></h1>
            <p><?php echo esc_html__('WordPress cookie banner with local runtime, wp-consent-api bridge, Google Consent Mode, and optional connected remote renderer.', 'trucookie-cmp-stable'); ?></p>

            <?php if ($onboarding) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?php echo esc_html__('Setup complete.', 'trucookie-cmp-stable'); ?></strong></p>
                    <p><?php echo esc_html__('Enable banner, choose regulation/language/theme, save, then test in incognito with ?tcs_reset_consent=1&tcs_force_banner=1.', 'trucookie-cmp-stable'); ?></p>
                </div>
            <?php endif; ?>

            <div class="tcs-card tcs-overview-wrap">
                <h2><?php echo esc_html__('Overview', 'trucookie-cmp-stable'); ?></h2>
                <div class="tcs-overview-grid">
                    <div class="tcs-overview-card">
                        <div class="tcs-overview-label"><?php echo esc_html__('Banner status', 'trucookie-cmp-stable'); ?></div>
                        <div class="tcs-overview-value"><?php echo esc_html($bannerStatus); ?></div>
                    </div>
                    <div class="tcs-overview-card">
                        <div class="tcs-overview-label"><?php echo esc_html__('Regulation', 'trucookie-cmp-stable'); ?></div>
                        <div class="tcs-overview-value"><?php echo esc_html($regulationLabel); ?></div>
                    </div>
                    <div class="tcs-overview-card">
                        <div class="tcs-overview-label"><?php echo esc_html__('Language', 'trucookie-cmp-stable'); ?></div>
                        <div class="tcs-overview-value"><?php echo esc_html($languageMap[$s['banner_language']] ?? 'Auto'); ?></div>
                    </div>
                    <div class="tcs-overview-card">
                        <div class="tcs-overview-label"><?php echo esc_html__('Geo-target banner', 'trucookie-cmp-stable'); ?></div>
                        <div class="tcs-overview-value"><?php echo esc_html($geoMap[$s['geo_target']] ?? 'Worldwide'); ?></div>
                    </div>
                    <div class="tcs-overview-card">
                        <div class="tcs-overview-label"><?php echo esc_html__('Renderer mode', 'trucookie-cmp-stable'); ?></div>
                        <div class="tcs-overview-value"><?php echo esc_html($modeMap[$s['mode']] ?? 'Auto'); ?></div>
                    </div>
                    <div class="tcs-overview-card">
                        <div class="tcs-overview-label"><?php echo esc_html__('Theme mode', 'trucookie-cmp-stable'); ?></div>
                        <div class="tcs-overview-value"><?php echo esc_html($schemeMap[$s['color_scheme']] ?? 'Auto'); ?></div>
                    </div>
                </div>

                <div class="tcs-quick-actions">
                    <a class="button button-secondary" href="https://trucookie.pro/login" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Log in to trucookie.pro', 'trucookie-cmp-stable'); ?></a>
                    <a class="button button-primary" href="https://trucookie.pro/register" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Create account in trucookie.pro', 'trucookie-cmp-stable'); ?></a>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('tcs_settings_group'); ?>

                <div class="tcs-card">
                <h2><?php echo esc_html__('Banner setup', 'trucookie-cmp-stable'); ?></h2>
                <table class="form-table tcs-form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Banner enabled', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[banner_enabled]" value="1" <?php checked($s['banner_enabled'], '1'); ?>>
                                <?php echo esc_html__('Show banner on frontend', 'trucookie-cmp-stable'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Regulation', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[regulation]">
                                <option value="gdpr" <?php selected($s['regulation'], 'gdpr'); ?>><?php echo esc_html__('GDPR', 'trucookie-cmp-stable'); ?></option>
                                <option value="us" <?php selected($s['regulation'], 'us'); ?>><?php echo esc_html__('US State Laws', 'trucookie-cmp-stable'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Language', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[banner_language]">
                                <option value="auto" <?php selected($s['banner_language'], 'auto'); ?>><?php echo esc_html__('Auto', 'trucookie-cmp-stable'); ?></option>
                                <option value="en" <?php selected($s['banner_language'], 'en'); ?>><?php echo esc_html__('English', 'trucookie-cmp-stable'); ?></option>
                                <option value="pl" <?php selected($s['banner_language'], 'pl'); ?>><?php echo esc_html__('Polski', 'trucookie-cmp-stable'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Geo-target banner', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[geo_target]">
                                <option value="worldwide" <?php selected($s['geo_target'], 'worldwide'); ?>><?php echo esc_html__('Worldwide', 'trucookie-cmp-stable'); ?></option>
                                <option value="eu-uk" <?php selected($s['geo_target'], 'eu-uk'); ?>><?php echo esc_html__('EU Countries & UK', 'trucookie-cmp-stable'); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html__('EU/UK mode uses server country headers when available (e.g. Cloudflare). If country cannot be detected, banner is shown by default.', 'trucookie-cmp-stable'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Mode', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[mode]">
                                <option value="auto" <?php selected($s['mode'], 'auto'); ?>><?php echo esc_html__('Auto', 'trucookie-cmp-stable'); ?></option>
                                <option value="local" <?php selected($s['mode'], 'local'); ?>><?php echo esc_html__('Local only', 'trucookie-cmp-stable'); ?></option>
                                <option value="connected" <?php selected($s['mode'], 'connected'); ?>><?php echo esc_html__('Connected (remote first)', 'trucookie-cmp-stable'); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html__('Connected mode requires "Remote banner URL". Without it plugin falls back to local renderer.', 'trucookie-cmp-stable'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Banner style', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[style]">
                                <option value="bar" <?php selected($s['style'], 'bar'); ?>><?php echo esc_html__('Bar (bottom centered)', 'trucookie-cmp-stable'); ?></option>
                                <option value="rectangle-left" <?php selected($s['style'], 'rectangle-left'); ?>><?php echo esc_html__('Rectangle left', 'trucookie-cmp-stable'); ?></option>
                                <option value="rectangle-right" <?php selected($s['style'], 'rectangle-right'); ?>><?php echo esc_html__('Rectangle right', 'trucookie-cmp-stable'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Color scheme', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[color_scheme]">
                                <option value="auto" <?php selected($s['color_scheme'], 'auto'); ?>><?php echo esc_html__('Auto', 'trucookie-cmp-stable'); ?></option>
                                <option value="light" <?php selected($s['color_scheme'], 'light'); ?>><?php echo esc_html__('White', 'trucookie-cmp-stable'); ?></option>
                                <option value="dark" <?php selected($s['color_scheme'], 'dark'); ?>><?php echo esc_html__('Dark', 'trucookie-cmp-stable'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Consent expiry (days)', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <input type="number" min="1" max="3650" step="1" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[consent_expiry_days]" value="<?php echo esc_attr($s['consent_expiry_days']); ?>">
                        </td>
                    </tr>
                </table>
                </div>

                <div class="tcs-card">
                <h2><?php echo esc_html__('Banner UX', 'trucookie-cmp-stable'); ?></h2>
                <table class="form-table tcs-form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Buttons', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[show_decline_button]" value="1" <?php checked($s['show_decline_button'], '1'); ?>>
                                <?php echo esc_html__('Show decline button', 'trucookie-cmp-stable'); ?>
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[show_preferences_button]" value="1" <?php checked($s['show_preferences_button'], '1'); ?>>
                                <?php echo esc_html__('Show preferences button', 'trucookie-cmp-stable'); ?>
                            </label>
                            <label style="display:block;">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[show_powered_by]" value="1" <?php checked($s['show_powered_by'], '1'); ?>>
                                <?php echo esc_html__('Show "Powered by TruCookie"', 'trucookie-cmp-stable'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Revisit consent button', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[show_revisit_button]" value="1" <?php checked($s['show_revisit_button'], '1'); ?>>
                                <?php echo esc_html__('Display floating revisit button after consent', 'trucookie-cmp-stable'); ?>
                            </label>
                            <input class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[revisit_button_text]" value="<?php echo esc_attr($s['revisit_button_text']); ?>">
                        </td>
                    </tr>
                </table>
                </div>

                <div class="tcs-card">
                <h2><?php echo esc_html__('Compliance & Integrations', 'trucookie-cmp-stable'); ?></h2>
                <table class="form-table tcs-form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Google Consent Mode v2', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[gcm_enabled]" value="1" <?php checked($s['gcm_enabled'], '1'); ?>>
                                <?php echo esc_html__('Enable GCM (default denied, update on consent)', 'trucookie-cmp-stable'); ?>
                            </label>
                            <label>
                                <?php echo esc_html__('wait_for_update (ms)', 'trucookie-cmp-stable'); ?>
                                <input type="number" min="0" max="5000" step="100" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[gcm_wait_for_update]" value="<?php echo esc_attr($s['gcm_wait_for_update']); ?>">
                            </label>
                            <p class="description" style="margin-top:8px;margin-bottom:0;">
                                <?php echo esc_html__('Recommended (Consent Mode without TCF): use the default TruCookie banner template text and links, including Google data responsibility.', 'trucookie-cmp-stable'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Script blocker', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[enable_script_blocker]" value="1" <?php checked($s['enable_script_blocker'], '1'); ?>>
                                <?php echo esc_html__('Enable deterministic script unlocker by category', 'trucookie-cmp-stable'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Use: <script type="text/plain" data-tc-category="analytics" src="..."></script>', 'trucookie-cmp-stable'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Respect DNT', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[respect_dnt]" value="1" <?php checked($s['respect_dnt'], '1'); ?>>
                                <?php echo esc_html__('When browser sends Do Not Track=1, set essential-only consent automatically.', 'trucookie-cmp-stable'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Consent log compliance', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[collect_user_metadata]" value="1" <?php checked($s['collect_user_metadata'], '1'); ?>>
                                <?php echo esc_html__('Collect technical metadata in local logs (IP hint and user agent).', 'trucookie-cmp-stable'); ?>
                            </label>
                            <label style="display:block;">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[forward_consent_logs]" value="1" <?php checked($s['forward_consent_logs'], '1'); ?>>
                                <?php echo esc_html__('Allow forwarding consent events to configured TruCookie endpoint.', 'trucookie-cmp-stable'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Enable these only if your privacy policy clearly discloses collection/transfer and your legal basis is in place.', 'trucookie-cmp-stable'); ?></p>
                        </td>
                    </tr>
                </table>
                </div>

                <div class="tcs-card">
                <h2><?php echo esc_html__('TruCookie.pro connection', 'trucookie-cmp-stable'); ?></h2>
                <table class="form-table tcs-form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Service URL', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <input class="regular-text" type="url" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[service_url]" value="<?php echo esc_attr($s['service_url']); ?>">
                            <p class="description"><?php echo esc_html__('Base URL of TruCookie service (for API calls).', 'trucookie-cmp-stable'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('API key', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <input class="regular-text" type="password" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[api_key]" value="<?php echo esc_attr($s['api_key']); ?>" autocomplete="off">
                            <p class="description"><?php echo esc_html__('Generate API key in trucookie.pro and paste it here. This key is used for consent logs API forwarding.', 'trucookie-cmp-stable'); ?></p>
                            <div class="tcs-quick-actions" style="margin-top:8px;">
                                <a class="button button-secondary" href="https://trucookie.pro/login" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Log in', 'trucookie-cmp-stable'); ?></a>
                                <a class="button button-primary" href="https://trucookie.pro/register" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Create account', 'trucookie-cmp-stable'); ?></a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Remote banner URL (optional)', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <input class="regular-text" type="url" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[remote_banner_url]" value="<?php echo esc_attr($s['remote_banner_url']); ?>">
                            <p class="description"><?php echo esc_html__('If set, connected mode will try this URL first. If remote script fails, plugin falls back to local banner automatically.', 'trucookie-cmp-stable'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Consent log path', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <input class="regular-text" type="text" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[log_path]" value="<?php echo esc_attr($s['log_path']); ?>">
                            <p class="description"><?php echo esc_html__('Path appended to Service URL for server-side consent logging.', 'trucookie-cmp-stable'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Remote timeout (ms)', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <input type="number" min="1000" max="10000" step="100" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[remote_timeout_ms]" value="<?php echo esc_attr($s['remote_timeout_ms']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Debug mode', 'trucookie-cmp-stable'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[debug]" value="1" <?php checked($s['debug'], '1'); ?>>
                                <?php echo esc_html__('Expose diagnostics and include remote response body in REST result.', 'trucookie-cmp-stable'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                </div>

                <?php submit_button(__('Save settings', 'trucookie-cmp-stable')); ?>
            </form>

            <div class="tcs-card">
                <h2><?php echo esc_html__('Diagnostics', 'trucookie-cmp-stable'); ?></h2>
                <p><code>window.trucookieCmp && window.trucookieCmp.diagnostics()</code></p>
                <p><code>window.scCmp && window.scCmp.openSettings()</code></p>
                <p><code><?php echo esc_html(rest_url('trucookie-cmp/v1/consent')); ?></code></p>
                <p class="tcs-help-note"><?php echo esc_html__('wp-consent-api integration is enabled. Categories are synced through wp_set_consent when consent is saved.', 'trucookie-cmp-stable'); ?></p>
            </div>

            <div class="tcs-card">
                <h2><?php echo esc_html__('Consent logs (local)', 'trucookie-cmp-stable'); ?></h2>
                <p><a class="button" href="<?php echo esc_url($exportUrl); ?>"><?php echo esc_html__('Export CSV', 'trucookie-cmp-stable'); ?></a></p>

            <?php if (empty($logs)) : ?>
                <p><?php echo esc_html__('No logs yet.', 'trucookie-cmp-stable'); ?></p>
            <?php else : ?>
                <table class="widefat striped tcs-logs-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Time', 'trucookie-cmp-stable'); ?></th>
                            <th><?php echo esc_html__('Site URL', 'trucookie-cmp-stable'); ?></th>
                            <th><?php echo esc_html__('Analytics', 'trucookie-cmp-stable'); ?></th>
                            <th><?php echo esc_html__('Marketing', 'trucookie-cmp-stable'); ?></th>
                            <th><?php echo esc_html__('URL', 'trucookie-cmp-stable'); ?></th>
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
}
