<?php

namespace TruCookieCMP\Core;

final class Settings
{
    public const OPTION_KEY = 'tcs_settings';
    public const LOG_OPTION_KEY = 'tcs_consent_logs';
    public const DEFAULT_CONSENT_EXPIRY_DAYS = 180;

    /**
     * @return array<string,string>
     */
    public static function defaults(): array
    {
        return [
            'banner_enabled' => '1',
            'mode' => 'auto',
            'service_url' => 'https://trucookie.pro',
            'remote_banner_url' => '',
            'api_key' => '',
            'log_path' => '/api/v1/consents/log',
            'forward_consent_logs' => '0',
            'collect_user_metadata' => '0',
            'remote_timeout_ms' => '3500',
            'respect_dnt' => '1',
            'debug' => '0',
            'regulation' => 'gdpr',
            'banner_language' => 'auto',
            'geo_target' => 'worldwide',
            'color_scheme' => 'auto',
            'style' => 'bar',
            'show_decline_button' => '1',
            'show_preferences_button' => '1',
            'show_powered_by' => '1',
            'show_revisit_button' => '1',
            'revisit_button_text' => 'Privacy settings',
            'enable_script_blocker' => '1',
            'gcm_enabled' => '1',
            'gcm_wait_for_update' => '500',
            'consent_expiry_days' => (string) self::DEFAULT_CONSENT_EXPIRY_DAYS,
        ];
    }

    public static function ensure_defaults(): void
    {
        $existing = get_option(self::OPTION_KEY, null);
        if (!is_array($existing)) {
            update_option(self::OPTION_KEY, self::defaults());
            return;
        }

        $merged = array_merge(self::defaults(), $existing);
        if (
            (!isset($merged['remote_banner_url']) || (string) $merged['remote_banner_url'] === '')
            && !empty($existing['site_public_id'])
            && !empty($merged['service_url'])
        ) {
            $merged['remote_banner_url'] = rtrim((string) $merged['service_url'], '/') . '/banner.js?site=' . rawurlencode((string) $existing['site_public_id']);
        }
        if ($merged !== $existing) {
            update_option(self::OPTION_KEY, $merged);
        }
    }

    /**
     * @return array<string,string>
     */
    public function all(): array
    {
        $raw = get_option(self::OPTION_KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        return array_merge(self::defaults(), $raw);
    }

    public function get(string $key): string
    {
        $all = $this->all();
        return isset($all[$key]) ? (string) $all[$key] : '';
    }

    /**
     * @param mixed $input
     * @return array<string,string>
     */
    public function sanitize_input($input): array
    {
        $input = is_array($input) ? $input : [];
        $current = $this->all();

        $mode = isset($input['mode']) ? sanitize_key((string) $input['mode']) : $current['mode'];
        if (!in_array($mode, ['auto', 'local', 'connected'], true)) {
            $mode = 'auto';
        }

        $serviceUrl = isset($input['service_url']) ? esc_url_raw((string) $input['service_url']) : $current['service_url'];
        if ($serviceUrl === '') {
            $serviceUrl = self::defaults()['service_url'];
        }
        $serviceUrl = rtrim($serviceUrl, "/ \t\n\r\0\x0B");

        $remoteBannerUrl = isset($input['remote_banner_url']) ? esc_url_raw((string) $input['remote_banner_url']) : $current['remote_banner_url'];
        $remoteBannerUrl = trim($remoteBannerUrl);
        if ($remoteBannerUrl !== '' && !preg_match('#^https?://#i', $remoteBannerUrl)) {
            $remoteBannerUrl = '';
        }

        $apiKey = isset($input['api_key']) ? sanitize_text_field((string) $input['api_key']) : $current['api_key'];
        $apiKey = trim($apiKey);

        $logPath = isset($input['log_path']) ? sanitize_text_field((string) $input['log_path']) : $current['log_path'];
        $logPath = '/' . ltrim(trim($logPath), '/');
        if ($logPath === '/') {
            $logPath = self::defaults()['log_path'];
        }

        $timeout = isset($input['remote_timeout_ms']) ? (int) $input['remote_timeout_ms'] : (int) $current['remote_timeout_ms'];
        if ($timeout < 1000) {
            $timeout = 1000;
        }
        if ($timeout > 10000) {
            $timeout = 10000;
        }

        $style = isset($input['style']) ? sanitize_key((string) $input['style']) : $current['style'];
        if (!in_array($style, ['bar', 'rectangle-left', 'rectangle-right'], true)) {
            $style = 'bar';
        }

        $regulation = isset($input['regulation']) ? sanitize_key((string) $input['regulation']) : $current['regulation'];
        if (!in_array($regulation, ['gdpr', 'us'], true)) {
            $regulation = 'gdpr';
        }

        $bannerLanguage = isset($input['banner_language']) ? sanitize_key((string) $input['banner_language']) : $current['banner_language'];
        if (!in_array($bannerLanguage, ['auto', 'en', 'pl'], true)) {
            $bannerLanguage = 'auto';
        }

        $geoTarget = isset($input['geo_target']) ? sanitize_key((string) $input['geo_target']) : $current['geo_target'];
        if (!in_array($geoTarget, ['worldwide', 'eu-uk'], true)) {
            $geoTarget = 'worldwide';
        }

        $colorScheme = isset($input['color_scheme']) ? sanitize_key((string) $input['color_scheme']) : $current['color_scheme'];
        if (!in_array($colorScheme, ['auto', 'light', 'dark'], true)) {
            $colorScheme = 'auto';
        }

        $revisitButtonText = isset($input['revisit_button_text'])
            ? sanitize_text_field((string) $input['revisit_button_text'])
            : $current['revisit_button_text'];
        $revisitButtonText = trim($revisitButtonText);
        if ($revisitButtonText === '') {
            $revisitButtonText = self::defaults()['revisit_button_text'];
        }

        $gcmWait = isset($input['gcm_wait_for_update']) ? (int) $input['gcm_wait_for_update'] : (int) $current['gcm_wait_for_update'];
        if ($gcmWait < 0) {
            $gcmWait = 0;
        }
        if ($gcmWait > 5000) {
            $gcmWait = 5000;
        }

        $consentExpiryDays = isset($input['consent_expiry_days']) ? (int) $input['consent_expiry_days'] : (int) $current['consent_expiry_days'];
        if ($consentExpiryDays < 1) {
            $consentExpiryDays = self::DEFAULT_CONSENT_EXPIRY_DAYS;
        }
        if ($consentExpiryDays > 3650) {
            $consentExpiryDays = 3650;
        }

        return [
            'banner_enabled' => !empty($input['banner_enabled']) ? '1' : '0',
            'mode' => $mode,
            'service_url' => $serviceUrl,
            'remote_banner_url' => $remoteBannerUrl,
            'api_key' => $apiKey,
            'log_path' => $logPath,
            'forward_consent_logs' => !empty($input['forward_consent_logs']) ? '1' : '0',
            'collect_user_metadata' => !empty($input['collect_user_metadata']) ? '1' : '0',
            'remote_timeout_ms' => (string) $timeout,
            'respect_dnt' => !empty($input['respect_dnt']) ? '1' : '0',
            'debug' => !empty($input['debug']) ? '1' : '0',
            'regulation' => $regulation,
            'banner_language' => $bannerLanguage,
            'geo_target' => $geoTarget,
            'color_scheme' => $colorScheme,
            'style' => $style,
            'show_decline_button' => !empty($input['show_decline_button']) ? '1' : '0',
            'show_preferences_button' => !empty($input['show_preferences_button']) ? '1' : '0',
            'show_powered_by' => !empty($input['show_powered_by']) ? '1' : '0',
            'show_revisit_button' => !empty($input['show_revisit_button']) ? '1' : '0',
            'revisit_button_text' => $revisitButtonText,
            'enable_script_blocker' => !empty($input['enable_script_blocker']) ? '1' : '0',
            'gcm_enabled' => !empty($input['gcm_enabled']) ? '1' : '0',
            'gcm_wait_for_update' => (string) $gcmWait,
            'consent_expiry_days' => (string) $consentExpiryDays,
        ];
    }

    public function is_banner_enabled(): bool
    {
        return $this->get('banner_enabled') === '1';
    }

    public function is_connected_mode(): bool
    {
        $mode = $this->get('mode');
        if ($mode === 'local') {
            return false;
        }
        if ($mode === 'connected') {
            return $this->has_remote_banner_credentials();
        }

        return $this->has_remote_banner_credentials();
    }

    public function has_remote_banner_credentials(): bool
    {
        return $this->get_remote_banner_url() !== '';
    }

    public function has_api_credentials(): bool
    {
        return $this->get('service_url') !== '' && $this->get('api_key') !== '';
    }

    public function is_consent_log_forwarding_enabled(): bool
    {
        return $this->get('forward_consent_logs') === '1';
    }

    public function is_user_metadata_collection_enabled(): bool
    {
        return $this->get('collect_user_metadata') === '1';
    }

    public function get_remote_banner_url(): string
    {
        $url = trim($this->get('remote_banner_url'));
        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $url)) {
            return '';
        }

        return $url;
    }

    public function get_site_storage_key(): string
    {
        return 'tcs_cmp_consent_v1:' . md5((string) home_url('/'));
    }

    public function get_consent_expiry_days(): int
    {
        $days = (int) $this->get('consent_expiry_days');
        return $days > 0 ? $days : self::DEFAULT_CONSENT_EXPIRY_DAYS;
    }
}
