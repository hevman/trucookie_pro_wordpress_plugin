<?php

trait SC_AdminActionsBannerTrait
{
    private function output_wp_consent_api_bridge(): void
    {
        // WP Consent API integration (best-effort).
        // Declares opt-in consent type early and mirrors TruCookie decisions into WP Consent API categories.
        echo "\n" . '<script>(function(){' .
            'try{' .
                'if(typeof window.wp_consent_type==="undefined"||!window.wp_consent_type){' .
                    'window.wp_consent_type="optin";' .
                    'try{document.dispatchEvent(new CustomEvent("wp_consent_type_defined",{detail:{type:"optin"}}));}catch(e){}' .
                '}' .
            '}catch(e){}' .
            'var scLast={analytics:false,marketing:false};' .
            'function scApiReady(){try{return typeof window.wp_set_consent==="function";}catch(e){return false;}}' .
            'function scWpSet(cat,allow){' .
                'try{' .
                    'if(!scApiReady()) return false;' .
                    'window.wp_set_consent(String(cat), allow ? "allow" : "deny");' .
                    'return true;' .
                '}catch(e){}' .
                'return false;' .
            '}' .
            'function scApply(detail){' .
                'detail = detail || {};' .
                'var c = (detail.consent && typeof detail.consent==="object") ? detail.consent : detail;' .
                'scLast.analytics = !!c.analytics;' .
                'scLast.marketing = !!(c.marketing || c.ads);' .
                'scWpSet("statistics", scLast.analytics);' .
                'scWpSet("marketing", scLast.marketing);' .
            '}' .
            'function scApplyLast(){' .
                'scWpSet("statistics", scLast.analytics);' .
                'scWpSet("marketing", scLast.marketing);' .
            '}' .
            // Default: deny optional categories until the user chooses (opt-in).
            'scApply({analytics:false,marketing:false,ads:false});' .
            // If WP Consent API loads after our snippet, retry briefly to ensure defaults are set.
            'var scTries=0;' .
            'var scTimer=setInterval(function(){' .
                'scTries++;' .
                'if(scApiReady()){scApplyLast();clearInterval(scTimer);return;}' .
                'if(scTries>40){clearInterval(scTimer);}' .
            '},250);' .
            'try{' .
                'window.addEventListener("sc:consent", function(ev){ scApply(ev && ev.detail); });' .
                'document.addEventListener("sc:consent", function(ev){ scApply(ev && ev.detail); });' .
            '}catch(e){}' .
        '})();</script>' . "\n";
    }

    public function handle_connect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trucookie-cmp-consent-mode-v2'));
        }
        check_admin_referer('sc_connect');

        $apiKey = isset($_POST['api_key']) ? (string) wp_unslash($_POST['api_key']) : '';
        $apiKey = $this->sanitize_api_key($apiKey);
        if (trim($apiKey) === '') {
            $apiKey = $this->get_api_key();
        }

        $result = $this->connect_and_sync_site($this->get_service_url(), $apiKey);
        $msg = !empty($result['ok'])
            ? __('Connected.', 'trucookie-cmp-consent-mode-v2')
            : sprintf(__('Connect failed: %s', 'trucookie-cmp-consent-mode-v2'), (string) ($result['message'] ?? $result['error'] ?? __('unknown', 'trucookie-cmp-consent-mode-v2')));

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'sc_msg' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }

    public function handle_sync_site(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trucookie-cmp-consent-mode-v2'));
        }
        check_admin_referer('sc_sync_site');

        $serviceUrl = (string) get_option(self::OPT_SERVICE_URL, '');
        $apiKey = $this->get_api_key();

        if ($serviceUrl === '' || $apiKey === '') {
            wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'sc_msg' => rawurlencode(__('Requires API key.', 'trucookie-cmp-consent-mode-v2'))], admin_url('admin.php')));
            exit;
        }

        $res = $this->connect_and_sync_site($serviceUrl, $apiKey);
        $msg = !empty($res['ok'])
            ? __('Synced.', 'trucookie-cmp-consent-mode-v2')
            : sprintf(__('Sync failed: %s', 'trucookie-cmp-consent-mode-v2'), (string) ($res['message'] ?? $res['error'] ?? __('unknown', 'trucookie-cmp-consent-mode-v2')));

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'sc_msg' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }

    public function handle_check_banner(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trucookie-cmp-consent-mode-v2'));
        }
        check_admin_referer('sc_check_banner');

        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
        if (!$this->is_connected() || $sitePublicId === '') {
            wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'sc_msg' => rawurlencode(__('Requires API key.', 'trucookie-cmp-consent-mode-v2'))], admin_url('admin.php')));
            exit;
        }

        $res = $this->api_request('POST', '/sites/' . rawurlencode($sitePublicId) . '/banner/check', []);
        if (is_array($res)) {
            $this->set_last_banner_check([
                'ok' => !empty($res['ok']),
                'installed' => !empty($res['installed']),
                'checked_url' => (string) ($res['checked_url'] ?? ''),
                'error' => (string) ($res['error'] ?? ''),
            ]);
        }
        if (!empty($res['ok']) && !empty($res['installed'])) {
            $msg = __('Snippet detected.', 'trucookie-cmp-consent-mode-v2');
        } elseif (!empty($res['ok'])) {
            $msg = __('Snippet not detected yet.', 'trucookie-cmp-consent-mode-v2');
        } else {
            $msg = sprintf(__('Check failed: %s', 'trucookie-cmp-consent-mode-v2'), (string) ($res['message'] ?? $res['error'] ?? __('unknown', 'trucookie-cmp-consent-mode-v2')));
        }

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'sc_msg' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }

    private function handle_run_scan(string $scanType, string $nonceAction): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trucookie-cmp-consent-mode-v2'));
        }
        check_admin_referer($nonceAction);

        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
        if (!$this->is_connected() || $sitePublicId === '') {
            wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'sc_msg' => rawurlencode(__('Requires API key.', 'trucookie-cmp-consent-mode-v2'))], admin_url('admin.php')));
            exit;
        }

        $res = $this->api_request('POST', '/sites/' . rawurlencode($sitePublicId) . '/scan', [
            'scan_type' => $scanType,
        ]);

        $msg = !empty($res['ok'])
            ? __('Audit queued.', 'trucookie-cmp-consent-mode-v2')
            : sprintf(__('Audit failed: %s', 'trucookie-cmp-consent-mode-v2'), (string) ($res['message'] ?? $res['error'] ?? __('unknown', 'trucookie-cmp-consent-mode-v2')));
        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'sc_msg' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }

    public function handle_run_light_scan(): void
    {
        $this->handle_run_scan('light', 'sc_run_light_scan');
    }

    public function handle_run_deep_scan(): void
    {
        $this->handle_run_scan('deep', 'sc_run_deep_scan');
    }

    public function handle_track_upgrade_intent(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trucookie-cmp-consent-mode-v2'));
        }
        check_admin_referer('sc_track_upgrade_intent');

        // Support both GET (link navigation) and POST (sendBeacon/fetch keepalive).
        $intent = isset($_REQUEST['intent']) ? sanitize_key((string) wp_unslash($_REQUEST['intent'])) : 'unknown';
        $target = isset($_REQUEST['target']) ? (string) wp_unslash($_REQUEST['target']) : '';
        $target = esc_url_raw($target);
        $noRedirect = isset($_REQUEST['no_redirect']) && ((string) wp_unslash($_REQUEST['no_redirect'])) === '1';

        $allowed = [];
        $base = $this->get_service_url();
        if ($base !== '') {
            $allowed[] = $base;
        }

        $isAllowedTarget = false;
        if ($target !== '') {
            foreach ($allowed as $prefix) {
                if ($prefix !== '' && strpos($target, $prefix) === 0) {
                    $isAllowedTarget = true;
                    break;
                }
            }
        }

        if (!$isAllowedTarget) {
            wp_safe_redirect(add_query_arg([
                'page' => 'trucookie-cmp-consent-mode-v2',
                'sc_msg' => rawurlencode(__('Invalid redirect target.', 'trucookie-cmp-consent-mode-v2')),
            ], admin_url('admin.php')));
            exit;
        }

        $events = get_option(self::OPT_UPGRADE_INTENTS, []);
        $events = is_array($events) ? $events : [];
        $bucket = isset($events[$intent]) && is_int($events[$intent]) ? $events[$intent] : 0;
        $events[$intent] = $bucket + 1;
        $events['last_intent'] = $intent;
        $events['last_at'] = time();
        update_option(self::OPT_UPGRADE_INTENTS, $events);

        if ($noRedirect) {
            status_header(204);
            exit;
        }

        wp_safe_redirect($target);
        exit;
    }

    public function handle_disconnect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trucookie-cmp-consent-mode-v2'));
        }
        check_admin_referer('sc_disconnect');

        delete_option(self::OPT_API_KEY);
        delete_option(self::OPT_SITE_PUBLIC_ID);
        delete_option(self::OPT_VERIFICATION_TOKEN);
        delete_option(self::OPT_LAST_PLAN);
        delete_option(self::OPT_DASHBOARD_SITE_URL);
        delete_option(self::OPT_SITE_STATUS);
        delete_option(self::OPT_LAST_BANNER_CHECK);
        delete_option(self::OPT_LOCAL_BANNER_DIRTY);

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'sc_msg' => rawurlencode(__('Disconnected.', 'trucookie-cmp-consent-mode-v2'))], admin_url('admin.php')));
        exit;
    }

    public function handle_save_toggles(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trucookie-cmp-consent-mode-v2'));
        }
        check_admin_referer('sc_save_toggles');

        $injectBanner = !empty($_POST['inject_banner']) ? '1' : '0';
        $injectMeta = !empty($_POST['inject_meta']) ? '1' : '0';
        $autoSync = isset($_POST['auto_sync'])
            ? (!empty($_POST['auto_sync']) ? '1' : '0')
            : (string) get_option(self::OPT_AUTO_SYNC, '1');

        // Only available after connecting.
        if (! $this->is_connected()) {
            $injectMeta = '0';
            $autoSync = '0';
        }

        update_option(self::OPT_INJECT_BANNER, $injectBanner);
        update_option(self::OPT_INJECT_META, $injectMeta);
        update_option(self::OPT_AUTO_SYNC, $autoSync);

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'sc_msg' => rawurlencode(__('Saved.', 'trucookie-cmp-consent-mode-v2'))], admin_url('admin.php')));
        exit;
    }

    public function handle_verify(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trucookie-cmp-consent-mode-v2'));
        }
        check_admin_referer('sc_verify');

        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
        if ($sitePublicId === '') {
            wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'sc_msg' => rawurlencode(__('Not connected.', 'trucookie-cmp-consent-mode-v2'))], admin_url('admin.php')));
            exit;
        }

        $res = $this->api_request('POST', '/sites/' . rawurlencode($sitePublicId) . '/verify', []);
        $msg = !empty($res['ok'])
            ? __('Verified.', 'trucookie-cmp-consent-mode-v2')
            : sprintf(__('Verify failed: %s', 'trucookie-cmp-consent-mode-v2'), (string) ($res['message'] ?? $res['error'] ?? __('unknown', 'trucookie-cmp-consent-mode-v2')));

        if (!empty($res['ok'])) {
            $this->refresh_site_status_if_needed(true);
        }

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'sc_msg' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }

    private function banner_config_defaults(): array
    {
        return [
            'locale' => 'pl',
            'regionMode' => 'auto', // auto | eu | us
            'position' => 'bottom',
            'bannerSize' => 'standard',
            'showDeclineButton' => true,
            'showPreferencesButton' => true,
            'style' => 'bar',
            'primaryColor' => '#059669',
            'backgroundColor' => '#ffffff',
            'autoTheme' => true,
            'google' => [
                'mode' => 'none', // none | gtm | ga4 | advanced
                'gtmContainerId' => '',
                'ga4MeasurementId' => '',
                'googleAdsTagId' => '',
            ],
            'autoBlock' => [
                'enabled' => true,
            ],
            'telemetry' => [
                'consentLog' => false,
            ],
            'experimental' => [
                'networkBlocker' => false,
            ],
        ];
    }

    private function banner_payload_from_config(array $config): array
    {
        $config = array_replace_recursive($this->banner_config_defaults(), $config);

        $get = static fn(array $a, string $k, $d = null) => array_key_exists($k, $a) ? $a[$k] : $d;

        $bool = static function ($v, bool $d = false): bool {
            if (is_bool($v)) return $v;
            if (is_int($v)) return $v === 1;
            if (is_string($v)) {
                $vv = strtolower(trim($v));
                if (in_array($vv, ['1','true','yes','on'], true)) return true;
                if (in_array($vv, ['0','false','no','off'], true)) return false;
            }
            return $d;
        };

        return [
            'locale' => (string) ($get($config, 'locale', 'pl') ?: 'pl'),
            'position' => (string) ($get($config, 'position', 'bottom') ?: 'bottom'),
            'bannerSize' => (string) ($get($config, 'bannerSize', 'standard') ?: 'standard'),
            'showDeclineButton' => $bool($get($config, 'showDeclineButton', true), true) ? '1' : '0',
            'showPreferencesButton' => $bool($get($config, 'showPreferencesButton', true), true) ? '1' : '0',
            'style' => (string) ($get($config, 'style', 'bar') ?: 'bar'),
            'primaryColor' => (string) ($get($config, 'primaryColor', '#059669') ?: '#059669'),
            'backgroundColor' => (string) ($get($config, 'backgroundColor', '#ffffff') ?: '#ffffff'),
            'autoTheme' => $bool($get($config, 'autoTheme', true), true) ? '1' : '0',
            'googleIntegrationMode' => (string) ($get($get($config, 'google', []), 'mode', 'none') ?: 'none'),
            'gtmContainerId' => trim((string) $get($get($config, 'google', []), 'gtmContainerId', '')) ?: null,
            'ga4MeasurementId' => trim((string) $get($get($config, 'google', []), 'ga4MeasurementId', '')) ?: null,
            'googleAdsTagId' => trim((string) $get($get($config, 'google', []), 'googleAdsTagId', '')) ?: null,
            'autoBlockEnabled' => $bool($get($get($config, 'autoBlock', []), 'enabled', true), true) ? '1' : '0',
            'telemetryConsentLog' => $bool($get($get($config, 'telemetry', []), 'consentLog', false), false) ? '1' : '0',
            'experimentalNetworkBlocker' => $bool($get($get($config, 'experimental', []), 'networkBlocker', false), false) ? '1' : '0',
        ];
    }

    private function banner_config_from_local_option(): array
    {
        $raw = $this->get_local_banner_config();

        // Supported shapes:
        // - ['version' => int, 'config' => array]
        // - config array directly (legacy / manual)
        $cfg = is_array($raw['config'] ?? null) ? ($raw['config'] ?? []) : $raw;

        $defaults = $this->banner_config_defaults();
        $merged = array_replace_recursive($defaults, is_array($cfg) ? $cfg : []);

        return [
            'version' => isset($raw['version']) ? (int) $raw['version'] : null,
            'config' => $merged,
        ];
    }

    public function handle_pull_banner_config(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trucookie-cmp-consent-mode-v2'));
        }
        check_admin_referer('sc_pull_banner_config');

        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
        if (!$this->is_connected() || $sitePublicId === '') {
            wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'sc_msg' => rawurlencode(__('Requires API key.', 'trucookie-cmp-consent-mode-v2'))], admin_url('admin.php')));
            exit;
        }

        $res = $this->api_request('GET', '/sites/' . rawurlencode($sitePublicId) . '/banner/config');
        if (!empty($res['ok']) && is_array($res['banner'] ?? null)) {
            $this->set_local_banner_config($res['banner']);
            $msg = __('Banner pulled from dashboard.', 'trucookie-cmp-consent-mode-v2');
        } else {
            $msg = sprintf(__('Pull failed: %s', 'trucookie-cmp-consent-mode-v2'), (string) ($res['message'] ?? $res['error'] ?? __('unknown', 'trucookie-cmp-consent-mode-v2')));
        }

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'sc_msg' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }

    public function handle_save_banner_config(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trucookie-cmp-consent-mode-v2'));
        }
        check_admin_referer('sc_save_banner_config');

        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
        $connected = $this->is_connected() && $sitePublicId !== '';

        $get = static fn(string $k): string => isset($_POST[$k]) ? (string) wp_unslash($_POST[$k]) : '';
        $flag = static fn(string $k): string => !empty($_POST[$k]) ? '1' : '0';

        $payload = [
            'locale' => $this->sanitize_choice($get('locale'), ['pl', 'en', 'de', 'es', 'fr', 'it', 'pt_BR'], 'pl'),
            'regionMode' => $this->sanitize_choice($get('regionMode'), ['auto', 'eu', 'us'], 'auto'),
            'position' => $this->sanitize_choice($get('position'), ['bottom', 'bottom-left', 'bottom-right', 'top', 'top-left', 'top-right'], 'bottom'),
            'bannerSize' => $this->sanitize_choice($get('bannerSize'), ['standard', 'compact'], 'standard'),
            'showDeclineButton' => $flag('showDeclineButton'),
            'showPreferencesButton' => $flag('showPreferencesButton'),
            'style' => $this->sanitize_choice($get('style'), ['bar', 'rectangle-right', 'rectangle-left', 'elegant'], 'bar'),
            'primaryColor' => $this->sanitize_hex_color_or_default($get('primaryColor'), '#059669'),
            'backgroundColor' => $this->sanitize_hex_color_or_default($get('backgroundColor'), '#ffffff'),
            'autoTheme' => $flag('autoTheme'),
            'googleIntegrationMode' => $this->sanitize_choice($get('googleIntegrationMode'), ['none', 'gtm', 'ga4', 'advanced'], 'none'),
            'gtmContainerId' => $this->sanitize_tracking_id($get('gtmContainerId')),
            'ga4MeasurementId' => $this->sanitize_tracking_id($get('ga4MeasurementId')),
            'googleAdsTagId' => $this->sanitize_tracking_id($get('googleAdsTagId')),
            'autoBlockEnabled' => $flag('autoBlockEnabled'),
            'telemetryConsentLog' => $flag('telemetryConsentLog'),
            'experimentalNetworkBlocker' => $flag('experimentalNetworkBlocker'),
        ];

        // Keep config consistent and avoid accidental double counting.
        // - gtm: only GTM ID (tags managed inside GTM)
        // - ga4: only GA4 ID (no GTM)
        // - none: no IDs
        // - advanced: allow all fields as-is
        if ($payload['googleIntegrationMode'] === 'gtm') {
            $payload['ga4MeasurementId'] = null;
            $payload['googleAdsTagId'] = null;
        } elseif ($payload['googleIntegrationMode'] === 'ga4') {
            $payload['gtmContainerId'] = null;
            $payload['googleAdsTagId'] = null;
        } elseif ($payload['googleIntegrationMode'] === 'none') {
            $payload['gtmContainerId'] = null;
            $payload['ga4MeasurementId'] = null;
            $payload['googleAdsTagId'] = null;
        }

        if (!$connected) {
            $cfg = $this->banner_config_from_local_option();
            $newCfg = array_replace_recursive($cfg['config'] ?? [], [
                'locale' => $payload['locale'],
                'regionMode' => $payload['regionMode'],
                'position' => $payload['position'],
                'bannerSize' => $payload['bannerSize'],
                'showDeclineButton' => $payload['showDeclineButton'] === '1',
                'showPreferencesButton' => $payload['showPreferencesButton'] === '1',
                'style' => $payload['style'],
                'primaryColor' => $payload['primaryColor'],
                'backgroundColor' => $payload['backgroundColor'],
                'autoTheme' => $payload['autoTheme'] === '1',
                'google' => [
                    'mode' => $payload['googleIntegrationMode'] ?: 'none',
                    'gtmContainerId' => $payload['gtmContainerId'] ?: '',
                    'ga4MeasurementId' => $payload['ga4MeasurementId'] ?: '',
                    'googleAdsTagId' => $payload['googleAdsTagId'] ?: '',
                ],
                'autoBlock' => [
                    'enabled' => $payload['autoBlockEnabled'] === '1',
                ],
                'telemetry' => [
                    'consentLog' => $payload['telemetryConsentLog'] === '1',
                ],
                'experimental' => [
                    'networkBlocker' => $payload['experimentalNetworkBlocker'] === '1',
                ],
            ]);

            $this->set_local_banner_config([
                'version' => $cfg['version'] ?? null,
                'config' => $newCfg,
            ]);
            update_option(self::OPT_LOCAL_BANNER_DIRTY, '1');
            $msg = __('Saved locally. Connect to sync with the dashboard.', 'trucookie-cmp-consent-mode-v2');
        } else {
            // `regionMode` is guest-only (offline preview + local banner). Server-side uses geo detection.
            $toSend = $payload;
            unset($toSend['regionMode']);

            $res = $this->api_request('POST', '/sites/' . rawurlencode($sitePublicId) . '/banner/config', $toSend);
            if (!empty($res['ok']) && is_array($res['banner'] ?? null)) {
                $this->set_local_banner_config($res['banner']);
                update_option(self::OPT_LOCAL_BANNER_DIRTY, '0');
                $msg = __('Banner saved to dashboard.', 'trucookie-cmp-consent-mode-v2');
            } else {
                $msg = sprintf(__('Save failed: %s', 'trucookie-cmp-consent-mode-v2'), (string) ($res['message'] ?? $res['error'] ?? __('unknown', 'trucookie-cmp-consent-mode-v2')));
            }
        }

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp-consent-mode-v2', 'sc_msg' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }

    public function output_verification_meta(): void
    {
        $inject = (string) get_option(self::OPT_INJECT_META, '0');
        if ($inject !== '1') {
            return;
        }

        $token = (string) get_option(self::OPT_VERIFICATION_TOKEN, '');
        if ($token === '') {
            return;
        }

        // New brand name + legacy name (backward compatible).
        echo "\n" . '<meta name="trucookie-site-verification" content="' . esc_attr($token) . '">' . "\n";
    }

    public function output_banner_snippet(): void
    {
        $inject = (string) get_option(self::OPT_INJECT_BANNER, '0');
        if ($inject !== '1') {
            return;
        }

        // Prevent duplicate injection when multiple hooks run (wp_head + fallbacks).
        static $didOutput = false;
        if ($didOutput) {
            return;
        }
        $didOutput = true;

        $this->output_wp_consent_api_bridge();

        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
        $connected = $this->is_connected() && $sitePublicId !== '';

        if ($connected) {
            $base = $this->get_service_url();
            if ($base === '') {
                return;
            }

            $src = $base . '/banner.js?site=' . rawurlencode($sitePublicId);
            echo "\n" . '<script src="' . esc_url($src) . '"></script>' . "\n";
            return;
        }

        $local = $this->banner_config_from_local_option();
        $cfg = is_array($local['config'] ?? null) ? ($local['config'] ?? []) : [];
        $cfg = array_replace_recursive($this->banner_config_defaults(), $cfg);

        $privacyUrl = function_exists('get_privacy_policy_url') ? (string) get_privacy_policy_url() : '';
        $cookiesUrl = '';
        if (function_exists('get_page_by_path') && function_exists('get_permalink')) {
            $cookiesPage = get_page_by_path('cookies', OBJECT, 'page');
            if ($cookiesPage instanceof WP_Post) {
                $cookiesUrl = (string) get_permalink($cookiesPage);
            }
        }
        if ($cookiesUrl === '') {
            $cookiesUrl = $privacyUrl !== '' ? $privacyUrl : home_url('/privacy-policy');
        }

        $payload = [
            'site' => parse_url(home_url('/'), PHP_URL_HOST) ?: 'wp',
            'configVersion' => 0,
            'geo' => null,
            'config' => [
                'locale' => $cfg['locale'] ?? 'pl',
                'regionMode' => $cfg['regionMode'] ?? 'auto',
                'position' => $cfg['position'] ?? 'bottom',
                'bannerSize' => $cfg['bannerSize'] ?? 'standard',
                'showDeclineButton' => !empty($cfg['showDeclineButton']),
                'showPreferencesButton' => !empty($cfg['showPreferencesButton']),
                'style' => $cfg['style'] ?? 'bar',
                'primaryColor' => $cfg['primaryColor'] ?? '#059669',
                'backgroundColor' => $cfg['backgroundColor'] ?? '#ffffff',
                'autoTheme' => array_key_exists('autoTheme', $cfg) ? (bool) $cfg['autoTheme'] : true,
                'cookiesUrl' => $cookiesUrl,
                'privacyUrl' => $privacyUrl ?: null,
                'google' => [
                    'gtmContainerId' => (string) ($cfg['google']['gtmContainerId'] ?? ''),
                    'ga4MeasurementId' => (string) ($cfg['google']['ga4MeasurementId'] ?? ''),
                    'googleAdsTagId' => (string) ($cfg['google']['googleAdsTagId'] ?? ''),
                ],
                'autoBlock' => [
                    'enabled' => !empty($cfg['autoBlock']['enabled']),
                ],
                'telemetry' => [
                    'consentLog' => false,
                ],
                'experimental' => [
                    'networkBlocker' => false,
                ],
            ],
        ];

        $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return;
        }
        echo "\n" . '<script type="application/json" id="sc-local-cmp-config">' . esc_html($json) . '</script>' . "\n";
        $asset = plugin_dir_path(self::PLUGIN_FILE) . 'assets/local-cmp-banner.js';
        $ver = file_exists($asset) ? (string) filemtime($asset) : '0.1.0';
        echo "\n" . '<script src="' . esc_url(plugins_url('assets/local-cmp-banner.js', self::PLUGIN_FILE)) . '?ver=' . rawurlencode($ver) . '" defer></script>' . "\n";
    }

}
