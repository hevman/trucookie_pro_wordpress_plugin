<?php

namespace TruCookieCMP\Frontend;

use TruCookieCMP\Core\Settings;

if (!defined('ABSPATH')) {
    exit;
}

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
        add_action('wp_head', [$this, 'output_site_verification_meta'], 1);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 1);
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
            TRUCOOKIE_CMP_PLUGIN_URL . 'assets/css/banner.css',
            [],
            TRUCOOKIE_CMP_VERSION
        );

        $handle = 'tcs-cmp-banner';
        wp_enqueue_script(
            $handle,
            TRUCOOKIE_CMP_PLUGIN_URL . 'assets/js/banner.js',
            [],
            TRUCOOKIE_CMP_VERSION,
            false
        );

        wp_localize_script($handle, '_tcsConfig', $this->build_frontend_config());

        if ($this->settings->is_connected_mode()) {
            $remoteSrc = $this->settings->get_remote_banner_url();
            if ($remoteSrc !== '') {
                wp_register_script(
                    self::REMOTE_SCRIPT_ID,
                    esc_url_raw($remoteSrc),
                    [],
                    TRUCOOKIE_CMP_VERSION,
                    false
                );
                wp_script_add_data(self::REMOTE_SCRIPT_ID, 'defer', true);
                $nonce = function_exists('wp_get_script_nonce') ? (string) wp_get_script_nonce() : '';
                if ($nonce !== '') {
                    wp_script_add_data(self::REMOTE_SCRIPT_ID, 'nonce', $nonce);
                }
                wp_enqueue_script(self::REMOTE_SCRIPT_ID);
            }
        }
    }

    public function output_gcm_bootstrap(): void
    {
        if (!$this->should_render_banner_front()) {
            return;
        }
        if ($this->settings->get('gcm_enabled') !== '1') {
            return;
        }
        if ($this->settings->get('gcm_mode') === 'basic') {
            return;
        }

        $wait = (int) $this->settings->get('gcm_wait_for_update');
        if ($wait < 0) {
            $wait = 0;
        }
        if ($wait > 5000) {
            $wait = 5000;
        }
        $defaultGlobal = $this->normalize_consent_state($this->settings->get('gcm_default_global'), 'denied');
        $defaultEea = $this->normalize_consent_state($this->settings->get('gcm_default_eea'), $defaultGlobal);
        $defaultUs = $this->normalize_consent_state($this->settings->get('gcm_default_us'), $defaultGlobal);

        $nonce = function_exists('wp_get_script_nonce') ? (string) wp_get_script_nonce() : '';
        $developerId = trim((string) $this->settings->get('gcm_developer_id'));
        $developerIdSet = '';
        if ($developerId !== '' && preg_match('/^[A-Za-z0-9_]{3,64}$/', $developerId) === 1) {
            $developerIdSet = "window.gtag('set','developer_id." . $developerId . "',true);";
        }
        $eeaPayload = wp_json_encode([
            'analytics_storage' => $defaultEea,
            'ad_storage' => $defaultEea,
            'ad_user_data' => $defaultEea,
            'ad_personalization' => $defaultEea,
            'wait_for_update' => $wait,
            'region' => $this->eea_region_codes(),
        ]);
        $usPayload = wp_json_encode([
            'analytics_storage' => $defaultUs,
            'ad_storage' => $defaultUs,
            'ad_user_data' => $defaultUs,
            'ad_personalization' => $defaultUs,
            'wait_for_update' => $wait,
            'region' => ['US'],
        ]);
        $globalPayload = wp_json_encode([
            'analytics_storage' => $defaultGlobal,
            'ad_storage' => $defaultGlobal,
            'ad_user_data' => $defaultGlobal,
            'ad_personalization' => $defaultGlobal,
            'wait_for_update' => $wait,
        ]);
        if (!is_string($eeaPayload) || !is_string($usPayload) || !is_string($globalPayload)) {
            return;
        }

        $script = 'window.dataLayer=window.dataLayer||[];';
        $script .= 'window.gtag=window.gtag||function(){window.dataLayer.push(arguments);};';
        $script .= "window.gtag('consent','default'," . $eeaPayload . ');';
        $script .= "window.gtag('consent','default'," . $usPayload . ');';
        $script .= "window.gtag('consent','default'," . $globalPayload . ');';
        $script .= $developerIdSet;

        $attributes = ['id' => 'tcs-gcm-bootstrap'];
        if ($nonce !== '') {
            $attributes['nonce'] = $nonce;
        }
        wp_print_inline_script_tag($script, $attributes);
    }

    public function output_site_verification_meta(): void
    {
        if (is_admin()) {
            return;
        }

        $token = trim($this->settings->get('verification_token'));
        if ($token === '') {
            return;
        }

        printf(
            "<meta name=\"trucookie-site-verification\" content=\"%s\" />\n",
            esc_attr($token)
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function build_frontend_config(): array
    {
        $mode = $this->settings->is_connected_mode() ? 'connected' : 'local';
        $gcmMode = $this->settings->get('gcm_mode');
        if (!in_array($gcmMode, ['advanced', 'basic'], true)) {
            $gcmMode = 'advanced';
        }

        $privacyUrl = $this->resolve_privacy_policy_url();
        $cookiesUrl = $this->resolve_cookie_policy_url();

        $locale = function_exists('determine_locale') ? (string) determine_locale() : (string) get_locale();
        $locale = strtolower($locale);
        $languageSetting = $this->settings->get('banner_language');
        if (in_array($languageSetting, ['pl', 'en', 'de'], true)) {
            $locale = $languageSetting;
        }
        $isPl = strpos($locale, 'pl') === 0;
        $isDe = strpos($locale, 'de') === 0;

        $revisit = $this->settings->get('revisit_button_text');
        if ($revisit === '') {
            $revisit = $isPl ? 'Ustawienia prywatnosci' : ($isDe ? 'Datenschutzeinstellungen' : 'Privacy settings');
        }

        $labelsEn = [
            'title' => 'Privacy settings',
            'body' => 'We use cookies to operate the site, measure traffic, and improve content. You can accept all cookies, reject optional cookies, or manage your preferences.',
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
            'analyticsDescription' => 'Audience measurement and site performance.',
            'marketingDescription' => 'Marketing and advertising features.',
            'revisitButton' => $revisit,
            'disclaimer' => 'You can change your choice at any time in Privacy settings.',
        ];

        /** @var array<string,string> $labelsPl */
        $labelsPl = (array) json_decode(
            '{"title":"Ustawienia prywatno\\u015bci","body":"U\\u017cywamy plik\\u00f3w cookie, aby obs\\u0142ugiwa\\u0107 serwis, mierzy\\u0107 ruch i ulepsza\\u0107 tre\\u015bci. Mo\\u017cesz zaakceptowa\\u0107 wszystkie pliki cookie, odrzuci\\u0107 opcjonalne albo zarz\\u0105dza\\u0107 preferencjami.","accept":"Akceptuj wszystkie","reject":"Tylko niezb\\u0119dne","preferences":"Preferencje","save":"Zapisz","close":"Zamknij","cookiesLinkLabel":"Polityka cookies","privacyLinkLabel":"Polityka prywatno\\u015bci","preferencesTitle":"Ustawienia cookies","analyticsLabel":"Analityka","marketingLabel":"Marketing","analyticsDescription":"Pomiar ruchu i wydajno\\u015bci serwisu.","marketingDescription":"Funkcje marketingowe i reklamowe.","disclaimer":"Swoj\\u0105 decyzj\\u0119 mo\\u017cesz zmieni\\u0107 w dowolnym momencie w ustawieniach prywatno\\u015bci."}',
            true
        );
        $labelsPl['revisitButton'] = $revisit;

        /** @var array<string,string> $labelsDe */
        $labelsDe = (array) json_decode(
            '{"title":"Datenschutzeinstellungen","body":"Wir verwenden Cookies, um die Website zu betreiben, den Traffic zu messen und Inhalte zu verbessern. Du kannst alle Cookies akzeptieren, optionale Cookies ablehnen oder deine Einstellungen verwalten.","accept":"Alle akzeptieren","reject":"Nur notwendige","preferences":"Einstellungen","save":"Speichern","close":"Schliessen","cookiesLinkLabel":"Cookie-Richtlinie","privacyLinkLabel":"Datenschutzerklarung","preferencesTitle":"Cookie-Einstellungen","analyticsLabel":"Analyse","marketingLabel":"Marketing","analyticsDescription":"Reichweitenmessung und Website-Performance.","marketingDescription":"Marketing- und Werbefunktionen.","disclaimer":"Du kannst deine Entscheidung jederzeit in den Datenschutzeinstellungen andern."}',
            true
        );
        $labelsDe['revisitButton'] = $revisit;

        $defaultLabels = $isPl ? $labelsPl : ($isDe ? $labelsDe : $labelsEn);
        $gcmDefaultGlobal = $this->normalize_consent_state($this->settings->get('gcm_default_global'), 'denied');
        $gcmDefaultEea = $this->normalize_consent_state($this->settings->get('gcm_default_eea'), $gcmDefaultGlobal);
        $gcmDefaultUs = $this->normalize_consent_state($this->settings->get('gcm_default_us'), $gcmDefaultGlobal);

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
            'googleDataResponsibilityUrl' => '',
            'restUrl' => esc_url_raw(rest_url('trucookie-cmp/v1/consent')),
            'style' => $this->settings->get('style'),
            'colorScheme' => $this->settings->get('color_scheme'),
            'autoTheme' => true,
            'showPoweredBy' => $this->settings->get('show_powered_by') === '1',
            'poweredByUrl' => 'https://trucookie.pro',
            'poweredByLogoUrl' => TRUCOOKIE_CMP_PLUGIN_URL . 'assets/image/favicon.svg',
            'poweredByLogoDataUrl' => $this->build_local_logo_data_url(),
            'showDeclineButton' => $this->settings->get('show_decline_button') === '1',
            'showPreferencesButton' => $this->settings->get('show_preferences_button') === '1',
            'showRevisitButton' => $this->settings->get('show_revisit_button') === '1',
            'revisitButtonText' => $revisit,
            'enableScriptBlocker' => $gcmMode === 'basic'
                ? true
                : ($this->settings->get('enable_script_blocker') === '1'),
            'consentExpiryDays' => $this->settings->get_consent_expiry_days(),
            'gcm' => [
                'enabled' => $this->settings->get('gcm_enabled') === '1',
                'mode' => $gcmMode,
                'developerId' => $this->settings->get('gcm_developer_id'),
                'waitForUpdate' => (int) $this->settings->get('gcm_wait_for_update'),
                'defaultConsent' => [
                    'global' => $gcmDefaultGlobal,
                    'eea' => $gcmDefaultEea,
                    'us' => $gcmDefaultUs,
                ],
            ],
            'theme' => [
                'primary' => '#047857',
                'background' => '#ffffff',
            ],
            'locale' => $isPl ? 'pl' : ($isDe ? 'de' : 'en'),
            'labelsByLocale' => [
                'en' => $labelsEn,
                'pl' => $labelsPl,
                'de' => $labelsDe,
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

    private function build_local_logo_data_url(): string
    {
        $logoPath = TRUCOOKIE_CMP_PLUGIN_DIR . 'assets/image/favicon.svg';
        if (!is_readable($logoPath)) {
            return '';
        }

        $raw = @file_get_contents($logoPath);
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        return 'data:image/svg+xml;utf8,' . rawurlencode($raw);
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

    private function normalize_consent_state(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        if ($value === 'granted') {
            return 'granted';
        }
        if ($value === 'denied') {
            return 'denied';
        }

        return $fallback === 'granted' ? 'granted' : 'denied';
    }

    /**
     * @return string[]
     */
    private function eea_region_codes(): array
    {
        return [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT',
            'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO', 'GB', 'CH',
        ];
    }

    private function detect_country_code(): string
    {
        $candidates = [
            $this->read_server_value('HTTP_CF_IPCOUNTRY'),
            $this->read_server_value('GEOIP_COUNTRY_CODE'),
            $this->read_server_value('HTTP_X_COUNTRY_CODE'),
        ];

        foreach ($candidates as $candidate) {
            $candidate = strtoupper(trim($candidate));
            if (preg_match('/^[A-Z]{2}$/', $candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function read_server_value(string $key): string
    {
        if (!isset($_SERVER[$key])) {
            return '';
        }

        return sanitize_text_field(wp_unslash((string) $_SERVER[$key]));
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
