<?php

namespace TruCookieCMP\Frontend;

use TruCookieCMP\Core\Settings;

final class Frontend
{
    private const REMOTE_SCRIPT_ID = 'tcs-remote-banner';

    /** @var Settings */
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;

        add_action('init', [$this, 'load_banner_context']);
        add_action('wp_head', [$this, 'output_gcm_bootstrap'], 0);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 1);
        add_action('wp_head', [$this, 'output_connected_script'], 2);
    }

    public function load_banner_context(): void
    {
        // Reserved for future preloading logic.
    }

    public function enqueue_scripts(): void
    {
        if (!$this->should_render_banner_front()) {
            return;
        }

        wp_enqueue_style(
            'tcs-cmp-banner-style',
            TCS_PLUGIN_URL . 'assets/css/banner.css',
            [],
            TCS_VERSION
        );

        $handle = 'tcs-cmp-banner';
        wp_enqueue_script(
            $handle,
            TCS_PLUGIN_URL . 'assets/js/banner.js',
            [],
            TCS_VERSION,
            false
        );

        wp_localize_script($handle, '_tcsConfig', $this->build_frontend_config());
    }

    public function output_gcm_bootstrap(): void
    {
        if (!$this->should_render_banner_front()) {
            return;
        }
        if ($this->settings->get('gcm_enabled') !== '1') {
            return;
        }

        $wait = (int) $this->settings->get('gcm_wait_for_update');
        if ($wait < 0) {
            $wait = 0;
        }
        if ($wait > 5000) {
            $wait = 5000;
        }

        $nonce = function_exists('wp_get_script_nonce') ? (string) wp_get_script_nonce() : '';
        $nonceAttr = $nonce !== '' ? ' nonce="' . esc_attr($nonce) . '"' : '';

        echo '<script id="tcs-gcm-bootstrap"' . $nonceAttr . '>'
            . 'window.dataLayer=window.dataLayer||[];'
            . 'window.gtag=window.gtag||function(){window.dataLayer.push(arguments);};'
            . "window.gtag('consent','default',{analytics_storage:'denied',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',wait_for_update:" . $wait . '});'
            . '</script>';
    }

    public function output_connected_script(): void
    {
        if (!$this->should_render_banner_front()) {
            return;
        }
        if (!$this->settings->is_connected_mode()) {
            return;
        }

        $src = $this->settings->get_remote_banner_url();
        if ($src === '') {
            return;
        }

        $nonce = function_exists('wp_get_script_nonce') ? (string) wp_get_script_nonce() : '';
        $nonceAttr = $nonce !== '' ? ' nonce="' . esc_attr($nonce) . '"' : '';

        echo '<script id="' . esc_attr(self::REMOTE_SCRIPT_ID) . '" src="' . esc_url($src) . '"' . $nonceAttr . ' defer data-tcs-remote="1"></script>';
    }

    /**
     * @return array<string,mixed>
     */
    private function build_frontend_config(): array
    {
        $mode = $this->settings->is_connected_mode() ? 'connected' : 'local';

        $privacyUrl = $this->resolve_privacy_policy_url();
        $cookiesUrl = $this->resolve_cookie_policy_url();

        $locale = function_exists('determine_locale') ? (string) determine_locale() : (string) get_locale();
        $locale = strtolower($locale);
        $languageSetting = $this->settings->get('banner_language');
        if (in_array($languageSetting, ['pl', 'en'], true)) {
            $locale = $languageSetting;
        }
        $isPl = strpos($locale, 'pl') === 0;

        $revisit = $this->settings->get('revisit_button_text');
        if ($revisit === '') {
            $revisit = $isPl ? 'Ustawienia prywatnosci' : 'Privacy settings';
        }

        $labelsEn = [
            'title' => 'Cookies & privacy',
            'body' => 'We use cookies to enhance your browsing experience, serve personalised ads or content, and analyse our traffic. By clicking "Accept All", you consent to our use of cookies.',
            'accept' => 'Accept All',
            'reject' => 'Essential only',
            'preferences' => 'Preferences',
            'save' => 'Save',
            'close' => 'Close',
            'cookiesLinkLabel' => 'Cookie Policy',
            'privacyLinkLabel' => 'Privacy Policy',
            'preferencesTitle' => 'Cookie preferences',
            'analyticsLabel' => 'Analytics',
            'marketingLabel' => 'Marketing',
            'analyticsDescription' => 'Traffic measurement (Google Analytics).',
            'marketingDescription' => 'Ads / remarketing (Google).',
            'revisitButton' => $revisit,
            'disclaimer' => 'Manage consent in Preferences. See Privacy Policy for details.',
        ];

        /** @var array<string,string> $labelsPl */
        $labelsPl = (array) json_decode(
            '{"title":"Cookies i prywatno\\u015b\\u0107","body":"U\\u017cywamy plik\\u00f3w cookie, aby ulepszy\\u0107 Twoje przegl\\u0105danie, wy\\u015bwietla\\u0107 spersonalizowane reklamy lub tre\\u015bci oraz analizowa\\u0107 ruch w serwisie. Klikaj\\u0105c \\\"Akceptuj wszystkie\\\", wyra\\u017casz zgod\\u0119 na u\\u017cywanie przez nas plik\\u00f3w cookie.","accept":"Akceptuj wszystkie","reject":"Tylko niezb\\u0119dne","preferences":"Preferencje","save":"Zapisz","close":"Zamknij","cookiesLinkLabel":"Polityka cookies","privacyLinkLabel":"Polityka prywatno\\u015bci","preferencesTitle":"Ustawienia cookies","analyticsLabel":"Analityka","marketingLabel":"Marketing","analyticsDescription":"Pomiar ruchu (Google Analytics).","marketingDescription":"Reklamy / remarketing (Google).","disclaimer":"Szczeg\\u00f3\\u0142y znajdziesz w Polityce cookies."}',
            true
        );
        $labelsPl['revisitButton'] = $revisit;

        $defaultLabels = $isPl ? $labelsPl : $labelsEn;

        return [
            'bannerEnabled' => $this->settings->is_banner_enabled(),
            'mode' => $mode,
            'serviceUrl' => $this->settings->get('service_url'),
            'remoteScriptUrl' => $mode === 'connected' ? $this->settings->get_remote_banner_url() : '',
            'remoteScriptId' => self::REMOTE_SCRIPT_ID,
            'remoteTimeoutMs' => (int) $this->settings->get('remote_timeout_ms'),
            'regulation' => $this->settings->get('regulation'),
            'geoTarget' => $this->settings->get('geo_target'),
            'localStorageKey' => $this->settings->get_site_storage_key(),
            'legacyStorageKeys' => ['tcs_cmp_consent_v1', 'sc_cmp_gcm_v2'],
            'cookieStorageKey' => $this->settings->get_site_storage_key(),
            'respectDnt' => $this->settings->get('respect_dnt') === '1',
            'debug' => $this->settings->get('debug') === '1',
            'forceParams' => ['tcs_force_banner', 'sc_force_banner'],
            'resetParams' => ['tcs_reset_consent', 'sc_reset_consent'],
            'cookiesUrl' => $cookiesUrl,
            'privacyUrl' => $privacyUrl,
            'restUrl' => esc_url_raw(rest_url('trucookie-cmp/v1/consent')),
            'style' => $this->settings->get('style'),
            'colorScheme' => $this->settings->get('color_scheme'),
            'autoTheme' => true,
            'showPoweredBy' => $this->settings->get('show_powered_by') === '1',
            'poweredByUrl' => 'https://trucookie.pro',
            'poweredByLogoUrl' => 'https://trucookie.pro/favicon.svg',
            'showDeclineButton' => $this->settings->get('show_decline_button') === '1',
            'showPreferencesButton' => $this->settings->get('show_preferences_button') === '1',
            'showRevisitButton' => $this->settings->get('show_revisit_button') === '1',
            'revisitButtonText' => $revisit,
            'enableScriptBlocker' => $this->settings->get('enable_script_blocker') === '1',
            'consentExpiryDays' => $this->settings->get_consent_expiry_days(),
            'gcm' => [
                'enabled' => $this->settings->get('gcm_enabled') === '1',
                'waitForUpdate' => (int) $this->settings->get('gcm_wait_for_update'),
            ],
            'theme' => [
                'primary' => '#059669',
                'background' => '#ffffff',
            ],
            'locale' => $isPl ? 'pl' : 'en',
            'labelsByLocale' => [
                'en' => $labelsEn,
                'pl' => $labelsPl,
            ],
            'labels' => $defaultLabels,
        ];
    }

    private function resolve_privacy_policy_url(): string
    {
        $url = function_exists('get_privacy_policy_url') ? (string) get_privacy_policy_url() : '';
        if ($this->is_valid_page_url($url)) {
            return $url;
        }

        $pageId = (int) get_option('wp_page_for_privacy_policy', 0);
        $url = $this->resolve_page_url_by_id($pageId);
        if ($url !== '') {
            return $url;
        }

        return $this->resolve_page_url_by_slugs([
            'privacy-policy',
            'privacy',
            'polityka-prywatnosci',
            'polityka-prywatnosci-i-cookies',
        ]);
    }

    private function resolve_cookie_policy_url(): string
    {
        $optionIds = [
            'cli_pg_policy_page_id',
            'cky_cookie_policy_page_id',
            'cookie_policy_page_id',
            'tcs_cookie_policy_page_id',
        ];

        foreach ($optionIds as $optionId) {
            $url = $this->resolve_page_url_by_id((int) get_option($optionId, 0));
            if ($url !== '') {
                return $url;
            }
        }

        return $this->resolve_page_url_by_slugs([
            'cookie-policy',
            'cookies-policy',
            'cookies',
            'polityka-cookies',
            'polityka-cookie',
            'polityka-plikow-cookie',
            'cookies-and-privacy-policy',
        ]);
    }

    private function resolve_page_url_by_id(int $pageId): string
    {
        if ($pageId <= 0 || get_post_status($pageId) !== 'publish') {
            return '';
        }

        $url = get_permalink($pageId);
        if (!is_string($url) || $url === '') {
            return '';
        }

        return esc_url_raw($url);
    }

    /**
     * @param string[] $slugs
     */
    private function resolve_page_url_by_slugs(array $slugs): string
    {
        foreach ($slugs as $slug) {
            $page = get_page_by_path($slug, OBJECT, 'page');
            if (!($page instanceof \WP_Post) || $page->post_status !== 'publish') {
                continue;
            }

            $url = get_permalink($page);
            if (is_string($url) && $url !== '') {
                return esc_url_raw($url);
            }
        }

        return '';
    }

    private function is_valid_page_url(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        return wp_http_validate_url($url) !== false;
    }

    private function should_render_banner_front(): bool
    {
        if (is_admin() || !$this->settings->is_banner_enabled()) {
            return false;
        }

        return $this->is_geo_target_match();
    }

    private function is_geo_target_match(): bool
    {
        $target = $this->settings->get('geo_target');
        if ($target !== 'eu-uk') {
            return true;
        }

        $country = $this->detect_country_code();
        if ($country === '') {
            // Unknown country: fallback to showing the banner.
            return true;
        }

        return in_array($country, $this->eu_uk_country_codes(), true);
    }

    private function detect_country_code(): string
    {
        $candidates = [
            isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? (string) $_SERVER['HTTP_CF_IPCOUNTRY'] : '',
            isset($_SERVER['GEOIP_COUNTRY_CODE']) ? (string) $_SERVER['GEOIP_COUNTRY_CODE'] : '',
            isset($_SERVER['HTTP_X_COUNTRY_CODE']) ? (string) $_SERVER['HTTP_X_COUNTRY_CODE'] : '',
        ];

        foreach ($candidates as $candidate) {
            $candidate = strtoupper(trim($candidate));
            if (preg_match('/^[A-Z]{2}$/', $candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @return string[]
     */
    private function eu_uk_country_codes(): array
    {
        return [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
            'IS', 'LI', 'NO',
            'GB', 'JE', 'GG', 'IM',
        ];
    }
}
