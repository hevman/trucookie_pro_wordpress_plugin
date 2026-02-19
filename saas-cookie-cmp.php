<?php
/**
 * Plugin Name: TruCookie CMP (Consent Mode v2)
 * Description: Connects your WordPress site to the TruCookie dashboard. Installs the CMP snippet, provides best-effort verification, and helps run audits.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.0
 * Author: TruCookie
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SaaS_Cookie_CMP_Plugin
{
    /**
     * Default dashboard base URL (used when no override is provided).
     *
     * Override options:
     * - wp-config.php: define('SC_SERVICE_URL', 'https://your-dashboard-domain.com');
     * - WP filter: add_filter('sc_default_service_url', fn() => 'https://...');
     */
    private const DEFAULT_SERVICE_URL = 'https://trucookie.pro';

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

        // Snippet + meta tag injection (runs early in <head>).
        add_action('wp_head', [$self, 'output_verification_meta'], 0);
        add_action('wp_head', [$self, 'output_banner_snippet'], 0);
    }

    public static function activate(): void
    {
        // Ensure the service URL is set even if the UI doesn't expose it.
        $existing = (string) get_option(self::OPT_SERVICE_URL, '');
        $existing = trim($existing);
        if ($existing !== '') {
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

        update_option(self::OPT_SERVICE_URL, rtrim(trim((string) $default), "/ \t\n\r\0\x0B"));
        if (get_option(self::OPT_AUTO_SYNC, null) === null) {
            update_option(self::OPT_AUTO_SYNC, '1');
        }
    }

    public function admin_menu(): void
    {
        add_menu_page(
            'TruCookie CMP',
            'TruCookie',
            'manage_options',
            'trucookie-cmp',
            [$this, 'render_settings_page'],
            'dashicons-shield',
            59
        );
    }

    private function normalize_base_url(string $url): string
    {
        $url = trim($url);
        $url = rtrim($url, "/ \t\n\r\0\x0B");
        return $url;
    }

    private function get_service_url(): string
    {
        // Highest priority: wp-config override.
        if (defined('SC_SERVICE_URL')) {
            $maybe = $this->normalize_base_url((string) constant('SC_SERVICE_URL'));
            if ($maybe !== '') {
                return $maybe;
            }
        }

        // Legacy / already saved option.
        $opt = $this->normalize_base_url((string) get_option(self::OPT_SERVICE_URL, ''));
        if ($opt !== '') {
            return $opt;
        }

        // Default (can be filtered by advanced users).
        $default = self::DEFAULT_SERVICE_URL;
        $filtered = apply_filters('sc_default_service_url', $default);
        if (is_string($filtered) && trim($filtered) !== '') {
            $default = $filtered;
        }

        return $this->normalize_base_url((string) $default);
    }

    private function get_local_banner_config(): array
    {
        $cfg = get_option(self::OPT_BANNER_CONFIG, []);
        return is_array($cfg) ? $cfg : [];
    }

    private function set_local_banner_config(array $cfg): void
    {
        update_option(self::OPT_BANNER_CONFIG, $cfg);
    }

    private function get_site_status(): array
    {
        $raw = get_option(self::OPT_SITE_STATUS, []);
        return is_array($raw) ? $raw : [];
    }

    private function set_site_status(array $status): void
    {
        $status['updated_at'] = time();
        update_option(self::OPT_SITE_STATUS, $status);
    }

    private function set_last_banner_check(array $check): void
    {
        $check['checked_at'] = time();
        update_option(self::OPT_LAST_BANNER_CHECK, $check);
    }

    private function get_last_banner_check(): array
    {
        $raw = get_option(self::OPT_LAST_BANNER_CHECK, []);
        return is_array($raw) ? $raw : [];
    }

    private function default_locale_for_wp(): string
    {
        $wpLocale = (string) get_locale();
        return str_starts_with($wpLocale, 'pl') ? 'pl' : 'en';
    }

    private function api_request(string $method, string $path, array $body = null): array
    {
        $base = $this->get_service_url();
        if ($base === '') {
            return ['ok' => false, 'error' => 'missing_service_url'];
        }

        $apiKey = (string) get_option(self::OPT_API_KEY, '');
        if ($apiKey === '') {
            return ['ok' => false, 'error' => 'missing_api_key'];
        }

        $url = $base . '/api/plugin' . $path;

        $args = [
            'method' => strtoupper($method),
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Api-Key' => $apiKey,
            ],
        ];

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) {
            return ['ok' => false, 'error' => 'request_failed', 'message' => $res->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $raw = (string) wp_remote_retrieve_body($res);
        $json = json_decode($raw, true);

        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'invalid_json', 'status' => $code, 'body' => $raw];
        }

        if ($code < 200 || $code >= 300) {
            $json['ok'] = false;
            $json['status'] = $code;
        }

        return $json;
    }

    private function connect_and_sync_site(string $serviceUrl, string $apiKey): array
    {
        $serviceUrl = $this->normalize_base_url($serviceUrl);
        if ($serviceUrl === '') {
            $serviceUrl = $this->get_service_url();
        }

        update_option(self::OPT_SERVICE_URL, $serviceUrl);
        update_option(self::OPT_API_KEY, trim($apiKey));

        $me = $this->api_request('GET', '/me');
        if (empty($me['ok'])) {
            return $me;
        }

        if (isset($me['plan']['name'])) {
            update_option(self::OPT_LAST_PLAN, (string) $me['plan']['name']);
        }

        $defaultLocale = $this->default_locale_for_wp();

        $siteUrl = home_url('/');
        $ensure = $this->api_request('POST', '/sites/ensure', [
            'url' => $siteUrl,
            'default_locale' => $defaultLocale,
        ]);

        if (empty($ensure['ok'])) {
            return $ensure;
        }

        $site = $ensure['site'] ?? [];
        if (is_array($site)) {
            if (!empty($site['public_id'])) {
                update_option(self::OPT_SITE_PUBLIC_ID, (string) $site['public_id']);
            }
            if (!empty($site['verification_token'])) {
                update_option(self::OPT_VERIFICATION_TOKEN, (string) $site['verification_token']);
            }

            $this->set_site_status([
                'url' => (string) ($site['url'] ?? $siteUrl),
                'host' => (string) ($site['host'] ?? ''),
                'default_locale' => (string) ($site['default_locale'] ?? $defaultLocale),
                'is_verified' => !empty($site['is_verified']),
                'verified_via' => (string) ($site['verified_via'] ?? ''),
            ]);
        }

        $links = $ensure['links'] ?? [];
        if (is_array($links) && !empty($links['dashboard_site_onboarding'])) {
            update_option(self::OPT_DASHBOARD_SITE_URL, (string) $links['dashboard_site_onboarding']);
        }

        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
        if ($sitePublicId !== '') {
            // If user configured banner in guest mode, push it now (best-effort).
            $dirty = (string) get_option(self::OPT_LOCAL_BANNER_DIRTY, '0');
            if ($dirty === '1') {
                $local = $this->banner_config_from_local_option();
                $localCfg = is_array($local['config'] ?? null) ? ($local['config'] ?? []) : [];
                $push = $this->banner_payload_from_config($localCfg);
                $this->api_request('POST', '/sites/' . rawurlencode($sitePublicId) . '/banner/config', $push);
                update_option(self::OPT_LOCAL_BANNER_DIRTY, '0');
            }

            $banner = $this->api_request('GET', '/sites/' . rawurlencode($sitePublicId) . '/banner/config');
            if (!empty($banner['ok']) && is_array($banner['banner'] ?? null)) {
                $this->set_local_banner_config($banner['banner']);
            }
        }

        return $ensure;
    }

    /**
     * Best-effort refresh of site status (verified/host/ids) from the dashboard.
     * Throttled via transient to avoid extra API load.
     */
    private function refresh_site_status_if_needed(bool $force = false): void
    {
        if (! $this->is_connected()) {
            return;
        }

        $autoSync = (string) get_option(self::OPT_AUTO_SYNC, '1');
        if ($autoSync !== '1') {
            return;
        }

        $siteUrl = home_url('/');
        $throttleKey = 'sc_cmp_autosync_site_' . md5($siteUrl);
        if (!$force && get_transient($throttleKey)) {
            return;
        }

        $defaultLocale = $this->default_locale_for_wp();
        $ensure = $this->api_request('POST', '/sites/ensure', [
            'url' => $siteUrl,
            'default_locale' => $defaultLocale,
        ]);

        if (!empty($ensure['ok']) && is_array($ensure['site'] ?? null)) {
            $site = $ensure['site'];
            if (!empty($site['public_id'])) {
                update_option(self::OPT_SITE_PUBLIC_ID, (string) $site['public_id']);
            }
            if (!empty($site['verification_token'])) {
                update_option(self::OPT_VERIFICATION_TOKEN, (string) $site['verification_token']);
            }
            $this->set_site_status([
                'url' => (string) ($site['url'] ?? $siteUrl),
                'host' => (string) ($site['host'] ?? ''),
                'default_locale' => (string) ($site['default_locale'] ?? $defaultLocale),
                'is_verified' => !empty($site['is_verified']),
                'verified_via' => (string) ($site['verified_via'] ?? ''),
            ]);

            $links = $ensure['links'] ?? [];
            if (is_array($links) && !empty($links['dashboard_site_onboarding'])) {
                update_option(self::OPT_DASHBOARD_SITE_URL, (string) $links['dashboard_site_onboarding']);
            }
        }

        set_transient($throttleKey, '1', 300);
    }

    private function is_connected(): bool
    {
        $base = $this->get_service_url();
        $apiKey = (string) get_option(self::OPT_API_KEY, '');
        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');

        return $base !== '' && $apiKey !== '' && $sitePublicId !== '';
    }

    public function admin_enqueue_scripts(string $hookSuffix): void
    {
        if (!is_admin()) {
            return;
        }

        // Possible hook suffixes:
        // - toplevel_page_{menu_slug}
        // - settings_page_{menu_slug} (legacy)
        if (!in_array($hookSuffix, ['toplevel_page_trucookie-cmp', 'settings_page_trucookie-cmp'], true)) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        wp_register_style('sc-cmp-admin', false, [], '0.1.0');
        wp_enqueue_style('sc-cmp-admin');
        wp_add_inline_style('sc-cmp-admin', '
.sc-cmp-wrap{max-width:1200px}
.sc-header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:14px 0 8px}
.sc-header h1{margin:0;display:flex;align-items:center;gap:10px}
.sc-header .sc-sub{margin-top:2px}
.sc-pill{display:inline-flex;align-items:center;gap:6px;padding:3px 8px;border-radius:999px;border:1px solid #dcdcde;background:#fff;font-size:12px}
.sc-pill.is-ok{border-color:#86efac;background:#f0fdf4}
.sc-pill.is-warn{border-color:#fcd34d;background:#fffbeb}
.sc-pill.is-bad{border-color:#fecaca;background:#fef2f2}
.sc-tabs{margin-top:10px}
.sc-grid{display:grid;grid-template-columns:1fr 360px;gap:16px;align-items:start}
@media (max-width: 1100px){.sc-grid{grid-template-columns:1fr}}
.sc-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:14px}
.sc-card h2{margin:0 0 10px;font-size:14px}
.sc-muted{color:#646970}
.sc-kpis{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
@media (max-width: 1100px){.sc-kpis{grid-template-columns:1fr}}
.sc-kpi{border:1px solid #e5e7eb;border-radius:12px;padding:10px;background:#fff}
.sc-kpi .sc-kpi-title{font-size:12px;color:#646970;margin:0}
.sc-kpi .sc-kpi-value{font-size:14px;font-weight:600;margin:2px 0 0}
.sc-kpi .sc-kpi-value code{font-size:12px}
.sc-steps{margin:0;padding-left:18px}
.sc-steps li{margin:6px 0}
.sc-actions{display:flex;gap:8px;flex-wrap:wrap}
.sc-banner-grid{display:grid;grid-template-columns:minmax(380px,1fr) minmax(320px,420px);gap:16px;align-items:start}
@media (max-width: 1100px){.sc-banner-grid{grid-template-columns:1fr}}
.sc-preview-viewport{position:relative;height:360px;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7;overflow:hidden}
.sc-preview-viewport iframe{display:block;width:100%;height:100%;border:0;background:transparent}
.sc-preview-viewport .sc-local-preview{position:absolute;inset:0;overflow:hidden}
.sc-local-preview .sc-prev-page{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;padding:18px}
.sc-local-preview .sc-prev-frame{position:relative;width:100%;height:100%;border-radius:8px;overflow:hidden;border:1px solid rgba(0,0,0,0.08)}
.sc-local-preview .sc-prev-bg{position:absolute;inset:0}
.sc-local-preview .sc-prev-content{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:rgba(0,0,0,0.55);font-size:12px}
.sc-local-preview .sc-prev-banner{position:absolute;left:0;right:0;bottom:0;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between;padding:14px 16px;border-top:1px solid rgba(0,0,0,0.10);box-shadow:0 -10px 30px rgba(0,0,0,0.08);min-height:108px}
.sc-local-preview .sc-prev-banner.top{top:0;bottom:auto;border-top:0;border-bottom:1px solid rgba(0,0,0,0.10);box-shadow:0 10px 30px rgba(0,0,0,0.08)}
.sc-local-preview .sc-prev-banner.rect{max-width:420px;border-radius:14px;border:1px solid rgba(0,0,0,0.12)}
.sc-local-preview .sc-prev-banner.rect.right{left:auto;right:18px;bottom:18px}
.sc-local-preview .sc-prev-banner.rect.left{left:18px;right:auto;bottom:18px}
.sc-local-preview .sc-prev-title{font-weight:600;margin:0 0 4px;font-size:13px}
.sc-local-preview .sc-prev-body{margin:0;font-size:12px;line-height:1.35;opacity:0.92;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden}
.sc-local-preview .sc-prev-actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;margin-left:auto}
.sc-local-preview .sc-prev-btn{appearance:none;border:1px solid rgba(0,0,0,0.18);background:transparent;border-radius:10px;padding:8px 10px;font-size:12px;font-weight:600;cursor:pointer}
.sc-local-preview .sc-prev-btn.primary{border-color:transparent}
.sc-local-preview .sc-prev-links{margin-top:8px;font-size:12px;opacity:0.8;display:flex;gap:6px;align-items:center;flex-wrap:nowrap;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sc-local-preview .sc-prev-links a{overflow:hidden;text-overflow:ellipsis;max-width:52%}
.sc-local-preview .sc-prev-links a{color:inherit;text-decoration:underline;text-underline-offset:2px}
.sc-local-preview .sc-prev-modal{position:absolute;inset:0;background:rgba(0,0,0,0.42);display:flex;align-items:center;justify-content:center;padding:18px}
.sc-local-preview .sc-prev-dialog{width:min(520px,100%);border-radius:14px;border:1px solid rgba(0,0,0,0.18);box-shadow:0 20px 70px rgba(0,0,0,0.35);padding:14px}
.sc-local-preview .sc-prev-row{display:flex;gap:12px;justify-content:space-between;align-items:flex-start;padding:10px 0;border-top:1px solid rgba(0,0,0,0.10)}
.sc-local-preview .sc-prev-row:first-of-type{border-top:0}
.sc-local-preview .sc-prev-row strong{display:block;font-size:12px;margin-bottom:2px}
.sc-local-preview .sc-prev-row span{display:block;font-size:12px;opacity:0.85}
.sc-local-preview .sc-prev-check{margin-top:2px}
.sc-preview-toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 0}
.sc-badge{display:inline-flex;align-items:center;gap:6px;border:1px solid #dcdcde;background:#fff;border-radius:999px;padding:3px 8px;font-size:12px}
.sc-badge .dashicons{font-size:16px;line-height:16px}
');

        $inline = <<<'SCJS'
(function($){
  function base64UrlEncode(obj){
    try {
      var json = JSON.stringify(obj || {});
      var bytes = (typeof TextEncoder !== "undefined") ? new TextEncoder().encode(json) : null;
      var bin = "";
      if(bytes){
        for(var i=0;i<bytes.length;i++){ bin += String.fromCharCode(bytes[i]); }
      } else {
        bin = unescape(encodeURIComponent(json));
      }
      var b64 = btoa(bin);
      return b64.replace(/\\+/g,"-").replace(/\\//g,"_").replace(/=+$/g,"");
    } catch(e){ return ""; }
  }

  function readConfig(){
    return {
      locale: $("#locale").val() || "pl",
      regionMode: $("#regionMode").val() || "auto",
      position: $("#position").val() || "bottom",
      bannerSize: $("#bannerSize").val() || "standard",
      style: $("#style").val() || "bar",
      primaryColor: $("#primaryColor").val() || "#059669",
      backgroundColor: $("#backgroundColor").val() || "#ffffff",
      autoTheme: $("input[type=checkbox][name=autoTheme]").is(":checked"),
      showDeclineButton: $("input[type=checkbox][name=showDeclineButton]").is(":checked"),
      showPreferencesButton: $("input[type=checkbox][name=showPreferencesButton]").is(":checked")
    };
  }

  function effectiveTheme(cfg, theme){
    var t = String(theme || "auto").toLowerCase();
    if(t === "dark" || t === "light"){ return t; }
    try {
      if(window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches){
        return "dark";
      }
    } catch(e){}
    return "light";
  }

  function esc(s){
    return String(s || "").replace(/[&<>\"']/g, function(ch){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch] || ch);
    });
  }

  function copyFor(cfg){
    var isEn = (cfg && cfg.locale === "en");
    var isUs = (cfg && cfg.regionMode === "us");
    if(isEn){
      if(isUs){
        return {
          title: "Privacy choices",
          body: "We use essential cookies to make our site work. You can choose whether we can use optional cookies for analytics and ads. You can change your preferences at any time.",
          accept: "Accept",
          reject: "Reject",
          settings: "Preferences",
          prefs: "Privacy choices",
          analytics: "Analytics",
          analyticsDesc: "Traffic measurement (Google Analytics).",
          marketing: "Ads",
          marketingDesc: "Advertising / remarketing (Google).",
          disclaimer: "Details in the Cookie Policy.",
          learnMore: "Cookie Policy",
          privacy: "Privacy Policy",
          save: "Save",
          close: "Close"
        };
      }
      return {
        title: "Cookies & privacy",
        body: "We use essential cookies to make our site work. With your consent, we may also use optional cookies for analytics and marketing. By clicking “Accept”, you agree to optional cookies as described in our Cookie Policy. You can change your preferences at any time.",
        accept: "Accept",
        reject: "Essential only",
        settings: "Preferences",
        prefs: "Cookie settings",
        analytics: "Analytics",
        analyticsDesc: "Traffic measurement (Google Analytics).",
        marketing: "Marketing",
        marketingDesc: "Ads / remarketing (Google).",
        disclaimer: "Details in the Cookie Policy.",
        learnMore: "Cookie Policy",
        privacy: "Privacy Policy",
        save: "Save",
        close: "Close"
      };
    }
    if(isUs){
      return {
        title: "Twoje wybory prywatności",
        body: "Używamy niezbędnych cookies, aby serwis działał. Możesz zdecydować, czy pozwalasz nam na cookies opcjonalne do analityki i reklam. Preferencje możesz zmienić w każdej chwili.",
        accept: "Akceptuj",
        reject: "Odrzuć",
        settings: "Preferencje",
        prefs: "Ustawienia prywatności",
        analytics: "Analityka",
        analyticsDesc: "Pomiar ruchu (Google Analytics).",
        marketing: "Reklamy",
        marketingDesc: "Reklamy / remarketing (Google).",
        disclaimer: "Szczegóły znajdziesz w Polityce cookies.",
        learnMore: "Polityka cookies",
        privacy: "Polityka prywatności",
        save: "Zapisz",
        close: "Zamknij"
      };
    }
    return {
      title: "Cookies i prywatność",
      body: "Używamy niezbędnych cookies, aby serwis działał. Za Twoją zgodą możemy też używać cookies opcjonalnych do analityki i marketingu. Klikając „Akceptuj”, zgadzasz się na cookies opcjonalne opisane w Polityce cookies. Preferencje możesz zmienić w każdej chwili.",
      accept: "Akceptuj",
      reject: "Tylko niezbędne",
      settings: "Preferencje",
      prefs: "Ustawienia cookies",
      analytics: "Analityka",
      analyticsDesc: "Pomiar ruchu (Google Analytics).",
      marketing: "Marketing",
      marketingDesc: "Reklamy / remarketing (Google).",
      disclaimer: "Szczegóły znajdziesz w Polityce cookies.",
      learnMore: "Polityka cookies",
      privacy: "Polityka prywatności",
      save: "Zapisz",
      close: "Zamknij"
    };
  }

  function renderLocal(cfg, theme){
    var $root = $("#sc-local-preview");
    if(!$root.length){ return; }

    function hexToRgb(hex){
      if(typeof hex !== 'string') return null;
      var h = (hex || '').trim().toLowerCase();
      if(!h) return null;
      if(h[0] === '#') h = h.slice(1);
      if(h.length === 3){ h = h[0]+h[0]+h[1]+h[1]+h[2]+h[2]; }
      if(h.length !== 6) return null;
      var r = parseInt(h.slice(0,2), 16);
      var g = parseInt(h.slice(2,4), 16);
      var b = parseInt(h.slice(4,6), 16);
      if(!isFinite(r) || !isFinite(g) || !isFinite(b)) return null;
      return { r:r, g:g, b:b };
    }
    function isDarkHex(hex){
      var rgb = hexToRgb(hex);
      if(!rgb) return null;
      var lum = (0.2126*rgb.r + 0.7152*rgb.g + 0.0722*rgb.b) / 255;
      return lum < 0.52;
    }
    function bannerTheme(){
      var wantsDark = effectiveTheme(cfg, theme) === 'dark';
      var autoTheme = !(cfg && cfg.autoTheme === false);
      var bg = (cfg && cfg.backgroundColor) ? String(cfg.backgroundColor) : '#ffffff';
      var bgNorm = bg.trim().toLowerCase();
      var bgIsDark = isDarkHex(bgNorm);
      if(bgIsDark === null) bgIsDark = wantsDark;
      if(autoTheme && wantsDark && (!bgNorm || bgNorm === '#ffffff' || bgNorm === '#fff')) {
        bg = '#18181b';
        bgIsDark = true;
      }
      var textColor = bgIsDark ? 'rgb(244,244,245)' : 'rgb(17,24,39)';
      var border = bgIsDark ? 'rgba(255,255,255,0.16)' : 'rgba(17,24,39,0.15)';
      var divider = bgIsDark ? 'rgba(255,255,255,0.12)' : 'rgba(17,24,39,0.10)';
      var btnBorder = bgIsDark ? 'rgba(255,255,255,0.22)' : 'rgba(17,24,39,0.18)';
      return { bg:bg, isDark:!!bgIsDark, text:textColor, border:border, divider:divider, btnBorder:btnBorder };
    }
    function applyBannerLayout(wrap){
      var sp = '12px';
      wrap.style.bottom = sp;
      wrap.style.top = '';
      wrap.style.margin = '0';
      wrap.style.width = 'calc(100% - 24px)';
      if(cfg.style === 'rectangle-right'){
        wrap.style.left = 'auto';
        wrap.style.right = sp;
        wrap.style.maxWidth = '360px';
        return;
      }
      if(cfg.style === 'rectangle-left'){
        wrap.style.left = sp;
        wrap.style.right = 'auto';
        wrap.style.maxWidth = '360px';
        return;
      }
      wrap.style.left = sp;
      wrap.style.right = sp;
      wrap.style.maxWidth = '960px';
      wrap.style.margin = '0 auto';
    }
    function el(tag, textContent){
      var e = document.createElement(tag);
      if(typeof textContent === 'string') e.textContent = textContent;
      return e;
    }
    function btn(label, primary){
      var th = bannerTheme();
      var b = el('button', label);
      b.type = 'button';
      b.style.cursor = 'pointer';
      b.style.borderRadius = '10px';
      b.style.padding = (cfg.bannerSize === 'compact') ? '8px 10px' : '10px 12px';
      b.style.fontSize = '13px';
      b.style.fontWeight = '600';
      b.style.border = '1px solid ' + th.btnBorder;
      b.style.background = primary ? (cfg.primaryColor || '#059669') : 'transparent';
      b.style.color = primary ? '#ffffff' : th.text;
      return b;
    }

    $root.empty();

    // Fake page viewport background (like backend preview page).
    var pageDark = effectiveTheme(cfg, theme) === 'dark';
    var pageBg = pageDark ? 'rgb(9, 9, 11)' : 'rgb(244, 244, 245)';
    var pageText = pageDark ? 'rgb(244, 244, 245)' : 'rgb(17, 24, 39)';

    var outer = document.createElement('div');
    outer.style.position = 'absolute';
    outer.style.inset = '0';
    outer.style.display = 'flex';
    outer.style.alignItems = 'stretch';
    outer.style.justifyContent = 'stretch';
    outer.style.padding = '16px';

    var frame = document.createElement('div');
    frame.style.position = 'relative';
    frame.style.width = '100%';
    frame.style.height = '100%';
    frame.style.borderRadius = '10px';
    frame.style.overflow = 'hidden';
    frame.style.border = pageDark ? '1px solid rgba(255,255,255,0.10)' : '1px solid rgba(0,0,0,0.08)';
    frame.style.background = pageBg;
    frame.style.color = pageText;

    var hint = document.createElement('div');
    hint.textContent = 'Preview';
    hint.style.position = 'absolute';
    hint.style.inset = '0';
    hint.style.display = 'flex';
    hint.style.alignItems = 'center';
    hint.style.justifyContent = 'center';
    hint.style.pointerEvents = 'none';
    hint.style.opacity = '0.35';
    hint.style.fontSize = '12px';
    hint.style.letterSpacing = '0.02em';

    function openModal(){
      var existing = frame.querySelector('[data-sc-prev-modal]');
      if(existing && existing.parentNode) existing.parentNode.removeChild(existing);

      var text = copyFor(cfg);
      var th = bannerTheme();

      var overlay = document.createElement('div');
      overlay.setAttribute('data-sc-prev-modal','1');
      overlay.style.position = 'absolute';
      overlay.style.inset = '0';
      overlay.style.background = 'rgba(0,0,0,0.55)';
      overlay.style.display = 'flex';
      overlay.style.alignItems = 'center';
      overlay.style.justifyContent = 'center';
      overlay.style.padding = '16px';

      var modal = document.createElement('div');
      modal.style.width = '100%';
      modal.style.maxWidth = '560px';
      modal.style.borderRadius = '16px';
      modal.style.border = '1px solid ' + th.btnBorder;
      modal.style.background = th.bg;
      modal.style.padding = '16px';
      modal.style.boxShadow = '0 20px 60px rgba(0,0,0,0.25)';
      modal.style.color = th.text;

      var head = document.createElement('div');
      head.style.display = 'flex';
      head.style.alignItems = 'center';
      head.style.justifyContent = 'space-between';
      head.style.gap = '12px';

      var h = el('div', text.prefs);
      h.style.fontWeight = '800';
      h.style.fontSize = '16px';
      var close = btn(text.close, false);
      close.onclick = function(){ overlay.parentNode && overlay.parentNode.removeChild(overlay); };
      head.appendChild(h);
      head.appendChild(close);

      var d = el('div', text.disclaimer);
      d.style.marginTop = '8px';
      d.style.fontSize = '12px';
      d.style.opacity = '0.9';

      function makeLink(href, label){
        var a = document.createElement('a');
        a.href = href;
        a.textContent = label;
        a.target = '_blank';
        a.rel = 'noopener';
        a.style.textDecoration = 'underline';
        a.style.color = (cfg.primaryColor || '#059669');
        return a;
      }
      var links = el('div');
      links.style.marginTop = '8px';
      links.style.fontSize = '12px';
      links.style.opacity = '0.9';
      links.appendChild(makeLink('#', text.learnMore));
      links.appendChild(document.createTextNode(' \u00b7 '));
      links.appendChild(makeLink('#', text.privacy));

      function toggleRow(label, desc, initial){
        var row = el('div');
        row.style.display = 'flex';
        row.style.alignItems = 'center';
        row.style.justifyContent = 'space-between';
        row.style.gap = '12px';
        row.style.padding = '10px 0';
        row.style.borderBottom = '1px solid ' + th.divider;
        var left = el('div');
        var l1 = el('div', label);
        l1.style.fontWeight = '700';
        l1.style.fontSize = '13px';
        var l2 = el('div', desc);
        l2.style.fontSize = '12px';
        l2.style.opacity = '0.85';
        l2.style.marginTop = '2px';
        left.appendChild(l1);
        left.appendChild(l2);
        var input = document.createElement('input');
        input.type = 'checkbox';
        input.checked = !!initial;
        input.style.width = '18px';
        input.style.height = '18px';
        input.style.accentColor = (cfg.primaryColor || '#059669');
        row.appendChild(left);
        row.appendChild(input);
        return row;
      }

      var rA = toggleRow(text.analytics, text.analyticsDesc, true);
      var rM = toggleRow(text.marketing, text.marketingDesc, false);
      rM.style.borderBottom = '0';

      var actions = el('div');
      actions.style.display = 'flex';
      actions.style.flexWrap = 'wrap';
      actions.style.gap = '8px';
      actions.style.justifyContent = 'flex-end';
      actions.style.marginTop = '12px';
      var save = btn(text.save, true);
      save.onclick = function(){ overlay.parentNode && overlay.parentNode.removeChild(overlay); };
      actions.appendChild(save);

      modal.appendChild(head);
      modal.appendChild(d);
      modal.appendChild(links);
      modal.appendChild(rA);
      modal.appendChild(rM);
      modal.appendChild(actions);
      overlay.appendChild(modal);
      overlay.onclick = function(e){ if(e && e.target === overlay){ overlay.parentNode && overlay.parentNode.removeChild(overlay);} };
      frame.appendChild(overlay);
    }

    // Banner (same vertical layout as the real renderer).
    var th = bannerTheme();
    var text = copyFor(cfg);
    var banner = document.createElement('div');
    banner.style.position = 'absolute';
    banner.style.zIndex = '10';
    banner.style.boxSizing = 'border-box';
    banner.style.boxShadow = '0 10px 30px rgba(0,0,0,0.15)';
    banner.style.border = '1px solid ' + th.border;
    banner.style.borderRadius = '16px';
    banner.style.padding = (cfg.bannerSize === 'compact') ? '12px' : '16px';
    banner.style.background = th.bg;
    banner.style.color = th.text;
    applyBannerLayout(banner);

    var title = el('div', text.title);
    title.style.fontWeight = '700';
    title.style.fontSize = '14px';
    var body = el('div', text.body);
    body.style.marginTop = '6px';
    body.style.fontSize = '13px';
    body.style.opacity = '0.9';

    var disc = el('div');
    disc.style.marginTop = '8px';
    disc.style.fontSize = '12px';
    disc.style.opacity = '0.85';
    disc.appendChild(document.createTextNode(text.disclaimer));
    var a = document.createElement('a');
    a.href = '#';
    a.textContent = text.learnMore;
    a.style.marginLeft = '8px';
    a.style.textDecoration = 'underline';
    a.style.color = (cfg.primaryColor || '#059669');
    disc.appendChild(document.createTextNode(' \u00b7 '));
    disc.appendChild(a);

    var actions = el('div');
    actions.style.display = 'flex';
    actions.style.flexWrap = 'wrap';
    actions.style.gap = '8px';
    actions.style.marginTop = '12px';

    var accept = btn(text.accept, true);
    accept.onclick = function(){};
    actions.appendChild(accept);

    if(cfg.showDeclineButton){
      var reject = btn(text.reject, false);
      reject.onclick = function(){};
      actions.appendChild(reject);
    }
    if(cfg.showPreferencesButton){
      var settings = btn(text.settings, false);
      settings.onclick = function(){ openModal(); };
      actions.appendChild(settings);
    }

    var btnList = [accept];
    if(cfg.showDeclineButton && reject) btnList.push(reject);
    if(cfg.showPreferencesButton && settings) btnList.push(settings);

    function layoutActions(){
      try {
        for(var i=0;i<btnList.length;i++){
          var b = btnList[i];
          if(!b) continue;
          try { b.style.width = ''; } catch(e){}
          try { b.style.minWidth = ''; } catch(e){}
          try { b.style.gridColumn = ''; } catch(e){}
        }

        actions.style.display = 'flex';
        actions.style.flexWrap = 'wrap';
        actions.style.alignItems = 'center';
        actions.style.justifyContent = 'flex-start';

        var wrapped = false;
        try {
          var firstTop = null;
          for(var k=0;k<btnList.length;k++){
            var bk = btnList[k];
            if(!bk) continue;
            var top = bk.offsetTop;
            if(firstTop === null) firstTop = top;
            else if(top > firstTop + 1){ wrapped = true; break; }
          }
        } catch(e){}

        if(wrapped){
          actions.style.display = 'grid';
          actions.style.gridAutoFlow = 'row';
          actions.style.gridTemplateColumns = (btnList.length >= 3) ? '1fr 1fr' : '1fr';
          actions.style.alignItems = 'stretch';
          actions.style.justifyContent = 'stretch';
          for(var j=0;j<btnList.length;j++){
            var bb = btnList[j];
            if(!bb) continue;
            bb.style.width = '100%';
            bb.style.minWidth = '0';
          }
          if(btnList.length >= 3 && btnList[0]) btnList[0].style.gridColumn = '1 / -1';
        }
      } catch(e){}
    }

    banner.appendChild(title);
    banner.appendChild(body);
    banner.appendChild(disc);
    banner.appendChild(actions);

    frame.appendChild(hint);
    frame.appendChild(banner);
    outer.appendChild(frame);
    $root.append(outer);
    try { layoutActions(); } catch(e){}
    try { if(window.requestAnimationFrame) window.requestAnimationFrame(function(){ try { layoutActions(); } catch(e){} }); } catch(e){}
  }

  function updatePreview(){
    var $wrap = $("#sc-banner-preview");
    var theme = String($wrap.data("theme") || "auto");

    var $f = $("#sc-banner-preview-iframe");
    var baseUrl = $f.length ? String($f.data("previewBase") || "") : "";
    if($f.length && baseUrl){
      var site = String($f.data("site") || "preview");
      var cfg = readConfig();
      var enc = base64UrlEncode(cfg);
      var src = baseUrl + "?site=" + encodeURIComponent(site) + "&theme=" + encodeURIComponent(theme);
      if(enc){ src += "&config=" + enc; }
      $f.attr("src", src);
      return;
    }

    renderLocal(readConfig(), theme);
  }

  var timer = null;
  function schedule(){
    if(timer){ clearTimeout(timer); }
    timer = setTimeout(updatePreview, 80);
    try { updateUnsaved(); } catch(e){}
  }

  var initialSnapshot = null;
  function updateUnsaved(){
    var $u = $("#sc-banner-unsaved");
    if(!$u.length){ return; }
    var connected = String($("#sc-banner-preview").data("connected") || "0") === "1";
    if(initialSnapshot === null){
      initialSnapshot = JSON.stringify(readConfig() || {});
    }
    var cur = JSON.stringify(readConfig() || {});
    if(cur !== initialSnapshot){
      $u.text(connected ? "Unsaved changes — click “Save to dashboard”." : "Unsaved changes — click “Save locally”.");
    } else {
      $u.text("");
    }
  }

  function initPickers(){
    if(!$.fn.wpColorPicker){ return; }
    ["#primaryColor","#backgroundColor"].forEach(function(sel){
      var $el = $(sel);
      if(!$el.length || $el.data("scPicker")){ return; }
      $el.wpColorPicker({change:function(){schedule();},clear:function(){schedule();}});
      $el.data("scPicker", true);
    });
  }

  $(function(){
    initPickers();
    $(document).on("change input", "#sc-banner-config-form select, #sc-banner-config-form input", function(){ schedule(); });
    $(document).on("click", "[data-sc-preview-theme]", function(e){
      e.preventDefault();
      var t = String($(this).attr("data-sc-preview-theme") || "auto");
      $("#sc-banner-preview").data("theme", t);
      updatePreview();
    });
    updatePreview();
    updateUnsaved();
  });
})(jQuery);
SCJS;

        wp_add_inline_script('wp-color-picker', $inline);
    }

    public function handle_connect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('sc_connect');

        $apiKey = isset($_POST['api_key']) ? (string) wp_unslash($_POST['api_key']) : '';

        $result = $this->connect_and_sync_site($this->get_service_url(), $apiKey);
        $msg = !empty($result['ok']) ? 'Connected.' : ('Connect failed: ' . ($result['message'] ?? $result['error'] ?? 'unknown'));

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp', 'sc_msg' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }

    public function handle_sync_site(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('sc_sync_site');

        $serviceUrl = (string) get_option(self::OPT_SERVICE_URL, '');
        $apiKey = (string) get_option(self::OPT_API_KEY, '');

        if ($serviceUrl === '' || $apiKey === '') {
            wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp', 'sc_msg' => rawurlencode('Requires API key.')], admin_url('admin.php')));
            exit;
        }

        $res = $this->connect_and_sync_site($serviceUrl, $apiKey);
        $msg = !empty($res['ok']) ? 'Synced.' : ('Sync failed: ' . ($res['message'] ?? $res['error'] ?? 'unknown'));

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp', 'sc_msg' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }

    public function handle_check_banner(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('sc_check_banner');

        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
        if (!$this->is_connected() || $sitePublicId === '') {
            wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp', 'sc_msg' => rawurlencode('Requires API key.')], admin_url('admin.php')));
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
            $msg = 'Snippet detected.';
        } elseif (!empty($res['ok'])) {
            $msg = 'Snippet not detected yet.';
        } else {
            $msg = 'Check failed: ' . ($res['message'] ?? $res['error'] ?? 'unknown');
        }

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp', 'sc_msg' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }

    private function handle_run_scan(string $scanType, string $nonceAction): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer($nonceAction);

        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
        if (!$this->is_connected() || $sitePublicId === '') {
            wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp', 'sc_msg' => rawurlencode('Requires API key.')], admin_url('admin.php')));
            exit;
        }

        $res = $this->api_request('POST', '/sites/' . rawurlencode($sitePublicId) . '/scan', [
            'scan_type' => $scanType,
        ]);

        $msg = !empty($res['ok']) ? 'Audit queued.' : ('Audit failed: ' . ($res['message'] ?? $res['error'] ?? 'unknown'));
        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp', 'sc_msg' => rawurlencode($msg)], admin_url('admin.php')));
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

    public function handle_disconnect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
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

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp', 'sc_msg' => rawurlencode('Disconnected.')], admin_url('admin.php')));
        exit;
    }

    public function handle_save_toggles(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
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

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp', 'sc_msg' => rawurlencode('Saved.')], admin_url('admin.php')));
        exit;
    }

    public function handle_verify(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('sc_verify');

        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
        if ($sitePublicId === '') {
            wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp', 'sc_msg' => rawurlencode('Not connected.')], admin_url('admin.php')));
            exit;
        }

        $res = $this->api_request('POST', '/sites/' . rawurlencode($sitePublicId) . '/verify', []);
        $msg = !empty($res['ok']) ? 'Verified.' : ('Verify failed: ' . ($res['message'] ?? $res['error'] ?? 'unknown'));

        if (!empty($res['ok'])) {
            $this->refresh_site_status_if_needed(true);
        }

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp', 'sc_msg' => rawurlencode($msg)], admin_url('admin.php')));
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
            wp_die('Forbidden');
        }
        check_admin_referer('sc_pull_banner_config');

        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
        if (!$this->is_connected() || $sitePublicId === '') {
            wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp', 'sc_msg' => rawurlencode('Requires API key.')], admin_url('admin.php')));
            exit;
        }

        $res = $this->api_request('GET', '/sites/' . rawurlencode($sitePublicId) . '/banner/config');
        if (!empty($res['ok']) && is_array($res['banner'] ?? null)) {
            $this->set_local_banner_config($res['banner']);
            $msg = 'Banner pulled from dashboard.';
        } else {
            $msg = 'Pull failed: ' . ($res['message'] ?? $res['error'] ?? 'unknown');
        }

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp', 'sc_msg' => rawurlencode($msg)], admin_url('admin.php')));
        exit;
    }

    public function handle_save_banner_config(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('sc_save_banner_config');

        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
        $connected = $this->is_connected() && $sitePublicId !== '';

        $get = static fn(string $k): string => isset($_POST[$k]) ? (string) wp_unslash($_POST[$k]) : '';
        $flag = static fn(string $k): string => !empty($_POST[$k]) ? '1' : '0';

        $payload = [
            'locale' => $get('locale') ?: 'pl',
            'regionMode' => $get('regionMode') ?: 'auto',
            'position' => $get('position') ?: 'bottom',
            'bannerSize' => $get('bannerSize') ?: 'standard',
            'showDeclineButton' => $flag('showDeclineButton'),
            'showPreferencesButton' => $flag('showPreferencesButton'),
            'style' => $get('style') ?: 'bar',
            'primaryColor' => $get('primaryColor') ?: '#059669',
            'backgroundColor' => $get('backgroundColor') ?: '#ffffff',
            'autoTheme' => $flag('autoTheme'),
            'gtmContainerId' => trim($get('gtmContainerId')) ?: null,
            'ga4MeasurementId' => trim($get('ga4MeasurementId')) ?: null,
            'googleAdsTagId' => trim($get('googleAdsTagId')) ?: null,
            'autoBlockEnabled' => $flag('autoBlockEnabled'),
            'telemetryConsentLog' => $flag('telemetryConsentLog'),
            'experimentalNetworkBlocker' => $flag('experimentalNetworkBlocker'),
        ];

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
            $msg = 'Saved locally. Connect to sync with the dashboard.';
        } else {
            // `regionMode` is guest-only (offline preview + local banner). Server-side uses geo detection.
            $toSend = $payload;
            unset($toSend['regionMode']);

            $res = $this->api_request('POST', '/sites/' . rawurlencode($sitePublicId) . '/banner/config', $toSend);
            if (!empty($res['ok']) && is_array($res['banner'] ?? null)) {
                $this->set_local_banner_config($res['banner']);
                update_option(self::OPT_LOCAL_BANNER_DIRTY, '0');
                $msg = 'Banner saved to dashboard.';
            } else {
                $msg = 'Save failed: ' . ($res['message'] ?? $res['error'] ?? 'unknown');
            }
        }

        wp_safe_redirect(add_query_arg(['page' => 'trucookie-cmp', 'sc_msg' => rawurlencode($msg)], admin_url('admin.php')));
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
        echo "\n" . '<meta name="trucookie-site-verification" content="' . esc_attr($token) . '">' . "\n";
        echo "\n" . '<meta name="trucookie-site-verification" content="' . esc_attr($token) . '">' . "\n";
    }

    public function output_banner_snippet(): void
    {
        $inject = (string) get_option(self::OPT_INJECT_BANNER, '0');
        if ($inject !== '1') {
            return;
        }

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
        $cookiesUrl = home_url('/cookies');

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

        echo "\n" . '<script id="sc-local-cmp-banner">' . "\n";
        echo "(function(){\n";
        echo "\"use strict\";\n";
        echo "window.dataLayer = window.dataLayer || [];\n";
        echo "function gtag(){window.dataLayer.push(arguments);} window.gtag = window.gtag || gtag;\n";
        echo "var payload = " . $json . ";\n";
        echo "var cfg = (payload && payload.config) ? payload.config : {};\n";
        echo "var STORAGE_KEY = 'sc_wp_cmp_gcm_v2';\n";
        echo "var COOKIE_KEY = 'sc_wp_cmp_gcm_v2';\n";
        echo "function safeJsonParse(v){ try{return JSON.parse(v);}catch(e){return null;} }\n";
        echo "function getCookie(name){ var m=document.cookie.match(new RegExp('(?:^|; )'+name.replace(/[.*+?^{}$()|[\\\\]\\\\]/g,'\\\\$&')+'=([^;]*)')); return m?decodeURIComponent(m[1]):null; }\n";
        echo "function setCookie(name,value,days){ var maxAge=days?('; Max-Age='+(days*24*60*60)):''; var secure=(location.protocol==='https:')?'; Secure':''; document.cookie=name+'='+encodeURIComponent(value)+'; Path=/' + maxAge + '; SameSite=Lax' + secure; }\n";
        echo "function gpcDetected(){ try { return !!(navigator && navigator.globalPrivacyControl===true); } catch(e){ return false; } }\n";
        echo "function detectRegion(){\n";
        echo "  try{ var m=(cfg && typeof cfg.regionMode==='string')?cfg.regionMode.toLowerCase():'auto'; if(m==='us') return 'us'; if(m==='eu') return 'eu'; }catch(e){}\n";
        echo "  try{ var tz = Intl && Intl.DateTimeFormat ? (Intl.DateTimeFormat().resolvedOptions().timeZone||'') : ''; if(/^America\\//.test(tz)) return 'us'; if(/^Europe\\//.test(tz)) return 'eu'; }catch(e){}\n";
        echo "  try{ var lang=(navigator && navigator.language)?String(navigator.language):''; if(/\\ben-us\\b/i.test(lang)) return 'us'; }catch(e){}\n";
        echo "  return 'eu';\n";
        echo "}\n";
        echo "var region = detectRegion();\n";
        echo "function isDarkMode(){\n";
        echo "  try{ var t=null; try{ t=window.localStorage?window.localStorage.getItem('theme'):null; }catch(e){ t=null; } if(t==='dark') return true; if(t==='light') return false; }catch(e){}\n";
        echo "  try{ if(document && document.documentElement && document.documentElement.classList && document.documentElement.classList.contains('dark')) return true; }catch(e){}\n";
        echo "  try{ return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches); }catch(e){}\n";
        echo "  return false;\n";
        echo "}\n";
        echo "function hexToRgb(hex){ if(typeof hex!=='string') return null; var h=(hex||'').trim().toLowerCase(); if(!h) return null; if(h[0]==='#') h=h.slice(1); if(h.length===3) h=h[0]+h[0]+h[1]+h[1]+h[2]+h[2]; if(h.length!==6) return null; var r=parseInt(h.slice(0,2),16), g=parseInt(h.slice(2,4),16), b=parseInt(h.slice(4,6),16); if(!isFinite(r)||!isFinite(g)||!isFinite(b)) return null; return {r:r,g:g,b:b}; }\n";
        echo "function isDarkHex(hex){ var rgb=hexToRgb(hex); if(!rgb) return null; var lum=(0.2126*rgb.r+0.7152*rgb.g+0.0722*rgb.b)/255; return lum<0.52; }\n";
        echo "function theme(){ var wantsDark=isDarkMode(); var autoTheme=!(cfg && cfg.autoTheme===false); var bg=(cfg && cfg.backgroundColor)?String(cfg.backgroundColor):'#ffffff'; var bgNorm=bg.trim().toLowerCase(); var bgIsDark=isDarkHex(bgNorm); if(bgIsDark===null) bgIsDark=wantsDark; if(autoTheme && wantsDark && (!bgNorm||bgNorm==='#ffffff'||bgNorm==='#fff')){ bg='#18181b'; bgIsDark=true; } var text=bgIsDark?'rgb(244,244,245)':'rgb(17,24,39)'; var border=bgIsDark?'rgba(255,255,255,0.16)':'rgba(17,24,39,0.15)'; var divider=bgIsDark?'rgba(255,255,255,0.12)':'rgba(17,24,39,0.10)'; var btnBorder=bgIsDark?'rgba(255,255,255,0.22)':'rgba(17,24,39,0.18)'; return {bg:bg,isDark:!!bgIsDark,text:text,border:border,divider:divider,btnBorder:btnBorder}; }\n";
        echo "var docLang=(document.documentElement&&document.documentElement.lang)||''; var isEn=(cfg.locale==='en') || (cfg.locale!=='pl' && /^en\\b/i.test(docLang));\n";
        echo "var text=isEn?{title:'Cookies & privacy',body:'We use essential cookies to make our site work. With your consent, we may also use optional cookies for analytics and marketing. By clicking “Accept”, you agree to optional cookies as described in our Cookie Policy. You can change your preferences at any time.',acceptAll:'Accept',rejectAll:'Reject',settings:'Preferences',save:'Save',close:'Close',analytics:'Analytics',marketing:'Marketing',analyticsDesc:'Traffic measurement (Google Analytics).',marketingDesc:'Ads / remarketing (Google).',prefs:'Cookie settings',learnMore:'Cookie Policy',privacy:'Privacy Policy',disclaimer:'Details in the Cookie Policy.',essentialOnly:'Essential only',gpcNote:'Global Privacy Control is enabled — ads stay off.'}:{title:'Cookies i prywatność',body:'Używamy niezbędnych cookies, aby serwis działał. Za Twoją zgodą możemy też używać cookies opcjonalnych do analityki i marketingu. Klikając „Akceptuj”, zgadzasz się na cookies opcjonalne opisane w Polityce cookies. Preferencje możesz zmienić w każdej chwili.',acceptAll:'Akceptuj',rejectAll:'Odrzuć',settings:'Preferencje',save:'Zapisz',close:'Zamknij',analytics:'Analityka',marketing:'Marketing',analyticsDesc:'Pomiar ruchu (Google Analytics).',marketingDesc:'Reklamy / remarketing (Google).',prefs:'Ustawienia cookies',learnMore:'Polityka cookies',privacy:'Polityka prywatności',disclaimer:'Szczegóły znajdziesz w Polityce cookies.',essentialOnly:'Tylko niezbędne',gpcNote:'Masz włączony Global Privacy Control — reklamy pozostaną wyłączone.'};\n";
        echo "if(region==='us'){ if(isEn){ text.title='Privacy choices'; text.prefs='Privacy choices'; text.marketing='Ads'; text.marketingDesc='Advertising / remarketing (Google).'; text.body='We use essential cookies to make our site work. You can choose whether we can use optional cookies for analytics and ads. You can change your preferences at any time.'; } else { text.title='Twoje wybory prywatności'; text.prefs='Ustawienia prywatności'; text.body='Używamy niezbędnych cookies, aby serwis działał. Możesz zdecydować, czy pozwalasz nam na cookies opcjonalne do analityki i reklam. Preferencje możesz zmienić w każdej chwili.'; } }\n";
        echo "if(region!=='us'){ text.rejectAll=text.essentialOnly; }\n";
        echo "gtag('consent','default',{analytics_storage:'denied',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',wait_for_update:500});\n";
        echo "function readStoredDecision(){ var raw=null; try{ raw=window.localStorage?window.localStorage.getItem(STORAGE_KEY):null; }catch(e){ raw=null; } if(!raw) raw=getCookie(COOKIE_KEY); if(!raw) return null; var p=safeJsonParse(raw); if(!p||typeof p!=='object') return null; return {analytics:!!p.analytics,marketing:!!p.marketing}; }\n";
        echo "function storeDecision(d){ var v=JSON.stringify({v:1,analytics:!!d.analytics,marketing:!!d.marketing}); try{ if(window.localStorage) window.localStorage.setItem(STORAGE_KEY,v);}catch(e){} setCookie(COOKIE_KEY,v,180); }\n";
        echo "function applyConsent(d){ var ads=!!d.marketing; var an=!!d.analytics; if(region==='us' && gpcDetected()){ ads=false; } gtag('consent','update',{analytics_storage:an?'granted':'denied',ad_storage:ads?'granted':'denied',ad_user_data:ads?'granted':'denied',ad_personalization:ads?'granted':'denied'}); try{ window.dispatchEvent(new CustomEvent('sc:consent',{detail:{analytics:an,marketing:ads}})); }catch(e){} }\n";
        echo "function purposeListFromAttr(v){ if(!v||typeof v!=='string') return []; return v.split(/[\\s,]+/).map(function(x){return (x||'').toLowerCase();}).filter(Boolean); }\n";
        echo "function hasPurpose(el,p){ var v=el.getAttribute('data-sc-purpose')||el.getAttribute('data-cmp-purpose'); var arr=purposeListFromAttr(v); return arr.indexOf(p)>=0; }\n";
        echo "function cloneScriptLike(srcEl,newEl){ var attrs=['src','async','defer','crossorigin','referrerpolicy','integrity','nonce']; for(var i=0;i<attrs.length;i++){ var a=attrs[i]; if(srcEl.hasAttribute(a)) newEl.setAttribute(a, srcEl.getAttribute(a)||''); } if(srcEl.id) newEl.id=srcEl.id; }\n";
        echo "function unlockByPurpose(p){ var scripts=document.querySelectorAll('script[type=\"text/plain\"][data-sc-purpose], script[type=\"text/plain\"][data-cmp-purpose]'); for(var i=0;i<scripts.length;i++){ var s=scripts[i]; if(!hasPurpose(s,p) && !(p==='marketing'&&hasPurpose(s,'ads')) && !(p==='ads'&&hasPurpose(s,'marketing'))) continue; var repl=document.createElement('script'); cloneScriptLike(s,repl); var ds=s.getAttribute('data-sc-src')||s.getAttribute('data-cmp-src'); if(ds){ repl.setAttribute('src', ds); } else { repl.text = s.text || s.textContent || ''; } s.parentNode && s.parentNode.replaceChild(repl,s);} var img=document.querySelectorAll('img[data-sc-src][data-sc-purpose], img[data-sc-src][data-cmp-purpose]'); for(var j=0;j<img.length;j++){ var im=img[j]; if(!hasPurpose(im,p) && !(p==='marketing'&&hasPurpose(im,'ads')) && !(p==='ads'&&hasPurpose(im,'marketing'))) continue; if(!im.getAttribute('src')) im.setAttribute('src', im.getAttribute('data-sc-src')||''); } var ifr=document.querySelectorAll('iframe[data-sc-src][data-sc-purpose], iframe[data-sc-src][data-cmp-purpose]'); for(var k=0;k<ifr.length;k++){ var fr=ifr[k]; if(!hasPurpose(fr,p) && !(p==='marketing'&&hasPurpose(fr,'ads')) && !(p==='ads'&&hasPurpose(fr,'marketing'))) continue; if(!fr.getAttribute('src')) fr.setAttribute('src', fr.getAttribute('data-sc-src')||''); } }\n";
        echo "function ensureGoogleTagsLoaded(d){ var g=(cfg.google&&typeof cfg.google==='object')?cfg.google:{}; var gtmId=g.gtmContainerId||null; var gaId=g.ga4MeasurementId||null; var adsId=g.googleAdsTagId||null; if(!d.analytics && !d.marketing) return; if(gtmId && !document.getElementById('sc-gtm-loader')){ var s=document.createElement('script'); s.id='sc-gtm-loader'; s.async=true; s.src='https://www.googletagmanager.com/gtm.js?id='+encodeURIComponent(gtmId); document.head&&document.head.appendChild(s); return; } var first=d.analytics?gaId:null; if(!first && d.marketing) first=adsId; if(!first) return; if(!document.getElementById('sc-gtag-loader')){ var gs=document.createElement('script'); gs.id='sc-gtag-loader'; gs.async=true; gs.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(first); document.head&&document.head.appendChild(gs); gtag('js', new Date()); } if(gaId && d.analytics){ try{ gtag('config', gaId, { anonymize_ip: true }); }catch(e){} } if(adsId && d.marketing){ try{ gtag('config', adsId); }catch(e){} } }\n";
        echo "function onDecision(d){ storeDecision(d); applyConsent(d); if(cfg.autoBlock && cfg.autoBlock.enabled){ if(d.analytics) unlockByPurpose('analytics'); if(d.marketing) unlockByPurpose('marketing'); } ensureGoogleTagsLoaded(d); }\n";
        echo "function el(tag, txt){ var e=document.createElement(tag); if(typeof txt==='string') e.textContent=txt; return e; }\n";
        echo "function btn(label, primary){ var th=theme(); var b=el('button',label); b.type='button'; b.style.cursor='pointer'; b.style.borderRadius='10px'; b.style.padding=(cfg.bannerSize==='compact')?'8px 10px':'10px 12px'; b.style.fontSize='13px'; b.style.fontWeight='600'; b.style.border='1px solid '+th.btnBorder; b.style.background=primary?(cfg.primaryColor||'#059669'):'transparent'; b.style.color=primary?'#ffffff':th.text; return b; }\n";
        echo "function applyBannerLayout(wrap){ var sp='12px'; wrap.style.margin='0'; wrap.style.width='calc(100% - 24px)'; if(cfg.position && String(cfg.position).indexOf('top')===0){ wrap.style.top=sp; wrap.style.bottom=''; } else { wrap.style.bottom=sp; wrap.style.top=''; } if(cfg.style==='rectangle-right'){ wrap.style.left='auto'; wrap.style.right=sp; wrap.style.maxWidth='360px'; return; } if(cfg.style==='rectangle-left'){ wrap.style.left=sp; wrap.style.right='auto'; wrap.style.maxWidth='360px'; return; } wrap.style.left=sp; wrap.style.right=sp; wrap.style.maxWidth='960px'; wrap.style.margin='0 auto'; }\n";
        echo "function openModal(onCloseBanner){ var existing=document.getElementById('sc-cmp-modal'); if(existing&&existing.parentNode) existing.parentNode.removeChild(existing); var overlay=el('div'); overlay.id='sc-cmp-modal'; overlay.style.position='fixed'; overlay.style.inset='0'; overlay.style.background='rgba(0,0,0,0.55)'; overlay.style.zIndex='2147483647'; overlay.style.display='flex'; overlay.style.alignItems='center'; overlay.style.justifyContent='center'; overlay.style.padding='16px'; var modal=el('div'); var th=theme(); modal.style.width='100%'; modal.style.maxWidth='560px'; modal.style.borderRadius='16px'; modal.style.border='1px solid '+th.btnBorder; modal.style.background=th.bg; modal.style.padding='16px'; modal.style.boxShadow='0 20px 60px rgba(0,0,0,0.25)'; modal.style.color=th.text; var h=el('div',text.prefs); h.style.fontWeight='800'; h.style.fontSize='16px'; var dsc=el('div',text.disclaimer); dsc.style.marginTop='8px'; dsc.style.fontSize='12px'; dsc.style.opacity='0.9'; var links=el('div'); links.style.marginTop='8px'; links.style.fontSize='12px'; links.style.opacity='0.9'; function mk(href,label){ var a=document.createElement('a'); a.href=href; a.textContent=label; a.target='_blank'; a.rel='noopener'; a.style.textDecoration='underline'; a.style.color=(cfg.primaryColor||'#059669'); return a; } try{ if(cfg.cookiesUrl){ links.appendChild(mk(cfg.cookiesUrl,text.learnMore)); } }catch(e){} try{ if(cfg.privacyUrl){ if(links.childNodes.length>0) links.appendChild(document.createTextNode(' · ')); links.appendChild(mk(cfg.privacyUrl,text.privacy)); } }catch(e){} var gpc=null; if(region==='us' && gpcDetected()){ gpc=el('div',text.gpcNote); gpc.style.marginTop='10px'; gpc.style.fontSize='12px'; gpc.style.opacity='0.9'; } function toggleRow(label, desc, initial){ var row=el('div'); var th=theme(); row.style.display='flex'; row.style.alignItems='center'; row.style.justifyContent='space-between'; row.style.gap='12px'; row.style.padding='10px 0'; row.style.borderBottom='1px solid '+th.divider; var left=el('div'); var l1=el('div',label); l1.style.fontWeight='700'; l1.style.fontSize='13px'; var l2=el('div',desc); l2.style.fontSize='12px'; l2.style.opacity='0.85'; l2.style.marginTop='2px'; left.appendChild(l1); left.appendChild(l2); var input=document.createElement('input'); input.type='checkbox'; input.checked=!!initial; input.style.width='18px'; input.style.height='18px'; input.style.accentColor=(cfg.primaryColor||'#059669'); row.appendChild(left); row.appendChild(input); return {row:row,input:input}; }\n";
        echo "var cur=readStoredDecision()||{analytics:false,marketing:false}; if(region==='us' && gpcDetected()) cur.marketing=false; var rA=toggleRow(text.analytics,text.analyticsDesc,cur.analytics); var rM=toggleRow(text.marketing,text.marketingDesc,cur.marketing); rM.row.style.borderBottom='none'; if(region==='us' && gpcDetected()){ try{ rM.input.checked=false; rM.input.disabled=true; rM.row.style.opacity='0.7'; }catch(e){} }\n";
        echo "var actions=el('div'); actions.style.display='flex'; actions.style.justifyContent='flex-end'; actions.style.gap='8px'; actions.style.marginTop='12px'; if(region!=='us'){ var ess=btn(text.essentialOnly,false); ess.onclick=function(){ overlay.parentNode&&overlay.parentNode.removeChild(overlay); if(typeof onCloseBanner==='function') onCloseBanner(); onDecision({analytics:false,marketing:false}); }; actions.appendChild(ess);} var closeBtn=btn(text.close,false); closeBtn.onclick=function(){ overlay.parentNode&&overlay.parentNode.removeChild(overlay); }; var saveBtn=btn(text.save,true); saveBtn.onclick=function(){ overlay.parentNode&&overlay.parentNode.removeChild(overlay); if(typeof onCloseBanner==='function') onCloseBanner(); onDecision({analytics:!!rA.input.checked,marketing:!!rM.input.checked}); }; actions.appendChild(closeBtn); actions.appendChild(saveBtn);\n";
        echo "modal.appendChild(h); modal.appendChild(dsc); if(links.childNodes.length>0) modal.appendChild(links); if(gpc) modal.appendChild(gpc); modal.appendChild(rA.row); modal.appendChild(rM.row); modal.appendChild(actions); overlay.appendChild(modal);\n";
        echo "overlay.onclick=function(e){ if(e && e.target===overlay){ overlay.parentNode&&overlay.parentNode.removeChild(overlay);} };\n";
        echo "document.body && document.body.appendChild(overlay);\n";
        echo "}\n";
        echo "try{ window.scCmp = window.scCmp || {}; window.scCmp.openSettings = function(){ try{ openModal(); }catch(e){} }; }catch(e){}\n";
        echo "function mountBanner(){ try{ var ex=document.getElementById('sc-cmp-banner'); if(ex&&ex.parentNode) ex.parentNode.removeChild(ex); }catch(e){} var wrap=el('div'); var th=theme(); wrap.id='sc-cmp-banner'; wrap.style.position='fixed'; wrap.style.boxSizing='border-box'; wrap.style.zIndex='2147483646'; wrap.style.boxShadow='0 10px 30px rgba(0,0,0,0.15)'; wrap.style.border='1px solid '+th.border; wrap.style.borderRadius='16px'; wrap.style.padding=(cfg.bannerSize==='compact')?'12px':'16px'; wrap.style.background=th.bg; wrap.style.color=th.text; applyBannerLayout(wrap); var t=el('div',text.title); t.style.fontWeight='700'; t.style.fontSize='14px'; var body=el('div',text.body); body.style.marginTop='6px'; body.style.fontSize='13px'; body.style.opacity='0.9'; var disc=el('div'); disc.style.marginTop='8px'; disc.style.fontSize='12px'; disc.style.opacity='0.85'; disc.appendChild(document.createTextNode(text.disclaimer)); var cUrl=cfg.cookiesUrl||null; if(cUrl){ var a=document.createElement('a'); a.href=cUrl; a.textContent=text.learnMore; a.target='_blank'; a.rel='noopener'; a.style.marginLeft='8px'; a.style.textDecoration='underline'; a.style.color=(cfg.primaryColor||'#059669'); disc.appendChild(document.createTextNode(' \\u00b7 ')); disc.appendChild(a);} var actions=el('div'); actions.style.display='flex'; actions.style.flexWrap='wrap'; actions.style.gap='8px'; actions.style.marginTop='12px'; var accept=btn(text.acceptAll,true); var reject=btn(text.rejectAll,false); var settings=btn(text.settings,false); var btnList=[accept]; if(cfg.showDeclineButton!==false) btnList.push(reject); if(cfg.showPreferencesButton!==false) btnList.push(settings); var cleanupLayout=null; function layout(){ try{ for(var i=0;i<btnList.length;i++){ var b=btnList[i]; if(!b) continue; try{ b.style.width=''; b.style.minWidth=''; b.style.gridColumn=''; }catch(e){} } actions.style.display='flex'; actions.style.flexWrap='wrap'; actions.style.alignItems='center'; actions.style.justifyContent='flex-start'; var wrapped=false; try{ var firstTop=null; for(var k=0;k<btnList.length;k++){ var bk=btnList[k]; if(!bk) continue; var top=bk.offsetTop; if(firstTop===null) firstTop=top; else if(top>firstTop+1){ wrapped=true; break; } } }catch(e){} if(wrapped){ actions.style.display='grid'; actions.style.gridAutoFlow='row'; actions.style.gridTemplateColumns=(btnList.length>=3)?'1fr 1fr':'1fr'; actions.style.alignItems='stretch'; actions.style.justifyContent='stretch'; for(var j=0;j<btnList.length;j++){ var bb=btnList[j]; if(!bb) continue; bb.style.width='100%'; bb.style.minWidth='0'; } if(btnList.length>=3 && btnList[0]) btnList[0].style.gridColumn='1 / -1'; } }catch(e){} } function close(){ try{ if(typeof cleanupLayout==='function') cleanupLayout(); }catch(e){} wrap.parentNode&&wrap.parentNode.removeChild(wrap);} accept.onclick=function(){ close(); onDecision({analytics:true,marketing:true}); }; reject.onclick=function(){ close(); onDecision({analytics:false,marketing:false}); }; settings.onclick=function(){ openModal(close); }; actions.appendChild(accept); if(cfg.showDeclineButton!==false) actions.appendChild(reject); if(cfg.showPreferencesButton!==false) actions.appendChild(settings); wrap.appendChild(t); wrap.appendChild(body); wrap.appendChild(disc); wrap.appendChild(actions); function mount(){ document.body && document.body.appendChild(wrap); try{ layout(); }catch(e){} try{ if(window.requestAnimationFrame) window.requestAnimationFrame(function(){ try{ layout(); }catch(e){} }); }catch(e){} try{ var onResize=function(){ try{ if(document.getElementById('sc-cmp-banner')) layout(); }catch(e){} }; window.addEventListener('resize', onResize); cleanupLayout=function(){ try{ window.removeEventListener('resize', onResize); }catch(e){} }; }catch(e){} } if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', mount);} else { mount(); } }\n";
        echo "function ensureAutoUnlockObserver(){ if(!(cfg.autoBlock && cfg.autoBlock.enabled)) return; try{ if(window.__sc_wp_cmp_observer) return; window.__sc_wp_cmp_observer=true; var mo=new MutationObserver(function(){ try{ var cur=readStoredDecision(); if(!cur) return; if(cur.analytics) unlockByPurpose('analytics'); if(cur.marketing) unlockByPurpose('marketing'); }catch(e){} }); mo.observe(document.documentElement,{childList:true,subtree:true}); }catch(e){} }\n";
        echo "function init(){ var existing=readStoredDecision(); if(existing){ applyConsent(existing); if(cfg.autoBlock && cfg.autoBlock.enabled){ if(existing.analytics) unlockByPurpose('analytics'); if(existing.marketing) unlockByPurpose('marketing'); } ensureGoogleTagsLoaded(existing); ensureAutoUnlockObserver(); return; } ensureAutoUnlockObserver(); mountBanner(); }\n";
        echo "init();\n";
        echo "})();\n";
        echo "</script>\n";
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }

        $serviceUrl = $this->get_service_url();
        $websiteUrl = home_url('/');
        $apiKey = (string) get_option(self::OPT_API_KEY, '');
        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
        $injectBanner = (string) get_option(self::OPT_INJECT_BANNER, '0');
        $injectMeta = (string) get_option(self::OPT_INJECT_META, '0');
        $lastPlan = (string) get_option(self::OPT_LAST_PLAN, '');
        $dashboardSiteUrl = (string) get_option(self::OPT_DASHBOARD_SITE_URL, '');
        $localBanner = $this->banner_config_from_local_option();
        $localBannerVersion = $localBanner['version'];
        $localBannerCfg = $localBanner['config'];

        $connected = $this->is_connected();

        if ($connected) {
            $this->refresh_site_status_if_needed();
            // Options may have been updated during auto-sync.
            $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');
            $dashboardSiteUrl = (string) get_option(self::OPT_DASHBOARD_SITE_URL, '');
        }

        $siteStatus = $this->get_site_status();
        $lastBannerCheck = $this->get_last_banner_check();

        $me = null;
        $canDeep = false;
        if ($connected) {
            $me = $this->api_request('GET', '/me');
            if (!empty($me['ok']) && is_array($me['plan'] ?? null)) {
                $lastPlan = (string) ($me['plan']['name'] ?? $lastPlan);
                $canDeep = (bool) ($me['plan']['can_run_deep_audit'] ?? false);
                update_option(self::OPT_LAST_PLAN, $lastPlan);
            }
        }
        $apiOk = $connected && is_array($me) && !empty($me['ok']);

        $autoSyncEnabled = (string) get_option(self::OPT_AUTO_SYNC, '1');

        // Best-effort auto-sync banner settings (so changes in dashboard/website show up in WP without manual pull).
        if ($connected && $autoSyncEnabled === '1' && $sitePublicId !== '') {
            $throttleKey = 'sc_cmp_autopull_banner_' . md5($sitePublicId);
            if (!get_transient($throttleKey)) {
                $remote = $this->api_request('GET', '/sites/' . rawurlencode($sitePublicId) . '/banner/config');
                if (!empty($remote['ok']) && is_array($remote['banner'] ?? null)) {
                    $remoteVersion = isset($remote['banner']['version']) ? (int) $remote['banner']['version'] : null;
                    if ($remoteVersion && (!$localBannerVersion || (int) $localBannerVersion !== $remoteVersion)) {
                        $this->set_local_banner_config($remote['banner']);
                        $localBanner = $this->banner_config_from_local_option();
                        $localBannerVersion = $localBanner['version'];
                        $localBannerCfg = $localBanner['config'];
                    }
                }
                set_transient($throttleKey, '1', 60);
            }
        }

        $latest = null;
        if ($connected && $sitePublicId !== '') {
            $latest = $this->api_request('GET', '/sites/' . rawurlencode($sitePublicId) . '/scan/latest');
        }

        $msg = isset($_GET['sc_msg']) ? (string) wp_unslash($_GET['sc_msg']) : '';

        $base = $this->normalize_base_url($serviceUrl);
        $auditUrl = $base ? ($base . '/audit') : '';
        $registerUrl = $base ? ($base . '/register') : '';
        $billingUrl = $base ? ($base . '/billing') : '';
        $pricingUrl = $base ? ($base . '/pricing') : '';
        $dashboardSiteUrl = $dashboardSiteUrl ? $dashboardSiteUrl : '';

        $tab = isset($_GET['tab']) ? (string) wp_unslash($_GET['tab']) : 'overview';
        $tabs = [
            'overview' => 'Overview',
            'banner' => 'Banner',
            'audit' => 'Audit',
            'plans' => 'Plans',
        ];
        if (!isset($tabs[$tab])) {
            $tab = 'overview';
        }

        ?>
        <div class="wrap sc-cmp-wrap">
            <div class="sc-header">
                <div>
                    <h1 style="margin-bottom:0;">
                        TruCookie
                        <?php if ($connected && $apiOk): ?>
                            <span class="sc-pill is-ok"><span class="dashicons dashicons-yes"></span>Connected</span>
                        <?php elseif ($connected): ?>
                            <span class="sc-pill is-bad"><span class="dashicons dashicons-dismiss"></span>API key invalid</span>
                        <?php else: ?>
                            <span class="sc-pill is-warn"><span class="dashicons dashicons-warning"></span>Not connected</span>
                        <?php endif; ?>
                    </h1>
                    <div class="sc-muted sc-sub">
                        Google Consent Mode v2 + banner + audits — one dashboard, synced to WordPress.
                    </div>
                </div>
                <div class="sc-actions">
                    <?php if ($dashboardSiteUrl): ?>
                        <a class="button button-primary" href="<?php echo esc_url($dashboardSiteUrl); ?>" target="_blank" rel="noreferrer">Open dashboard</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($msg): ?>
                <div class="notice notice-info is-dismissible"><p><?php echo esc_html($msg); ?></p></div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper sc-tabs" aria-label="TruCookie tabs">
                <?php foreach ($tabs as $key => $label): ?>
                    <?php
                    $u = add_query_arg(['page' => 'trucookie-cmp', 'tab' => $key], admin_url('admin.php'));
                    $cls = 'nav-tab' . ($tab === $key ? ' nav-tab-active' : '');
                    ?>
                    <a href="<?php echo esc_url($u); ?>" class="<?php echo esc_attr($cls); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>

            <?php if ($tab === 'overview'): ?>
                <div class="sc-grid" style="margin-top:14px;">
                    <div class="sc-card">
                        <h2>Setup</h2>
                        <p class="sc-muted" style="margin-top:0;">
                            API key is generated by the dashboard (Profile). Website URL is auto-detected from WordPress.
                        </p>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('sc_connect'); ?>
                            <input type="hidden" name="action" value="sc_connect" />

                            <table class="form-table" role="presentation" style="margin-top:0;">
                                <tr>
                                    <th scope="row"><label for="sc_website_url">Dashboard URL</label></th>
                                    <td>
                                        <input id="sc_website_url" type="url" class="regular-text" value="<?php echo esc_attr($websiteUrl); ?>" readonly />
                                        <p class="description">URL strony z zainstalowaną wtyczką (auto).</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="api_key">API key</label></th>
                                    <td>
                                        <input name="api_key" id="api_key" type="password" class="regular-text" value="<?php echo esc_attr($apiKey); ?>" placeholder="Paste API key" autocomplete="off" />
                                        <p class="description">Stored in WordPress options. Treat it like a password.</p>
                                    </td>
                                </tr>
                            </table>

                            <div class="sc-actions">
                                <?php submit_button($apiKey ? 'Reconnect' : 'Connect', 'primary', 'submit', false); ?>
                            </div>
                        </form>

                        <?php if ($apiKey): ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 10px;">
                                <?php wp_nonce_field('sc_disconnect'); ?>
                                <input type="hidden" name="action" value="sc_disconnect" />
                                <?php submit_button('Disconnect', 'secondary'); ?>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="sc-card">
                        <h2>Status</h2>

                        <div class="sc-kpis" style="margin-bottom:10px;">
                            <div class="sc-kpi">
                                <p class="sc-kpi-title">Verification</p>
                                <p class="sc-kpi-value">
                                    <?php if (!empty($siteStatus['is_verified'])): ?>
                                        <span class="sc-badge"><span class="dashicons dashicons-yes"></span>Verified</span>
                                    <?php elseif ($connected): ?>
                                        <span class="sc-badge"><span class="dashicons dashicons-minus"></span>Not verified</span>
                                    <?php else: ?>
                                        <span class="sc-badge"><span class="dashicons dashicons-minus"></span>—</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="sc-kpi">
                                <p class="sc-kpi-title">Snippet</p>
                                <p class="sc-kpi-value">
                                    <?php if (!empty($lastBannerCheck['installed'])): ?>
                                        <span class="sc-badge"><span class="dashicons dashicons-yes"></span>Detected</span>
                                    <?php elseif ($connected && array_key_exists('installed', $lastBannerCheck)): ?>
                                        <span class="sc-badge"><span class="dashicons dashicons-warning"></span>Not detected</span>
                                    <?php else: ?>
                                        <span class="sc-badge"><span class="dashicons dashicons-minus"></span>—</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <table class="widefat striped">
                            <tbody>
                            <tr>
                                <td style="width: 160px;"><strong>Mode</strong></td>
                                <td><?php echo $apiKey ? 'Connected' : 'Guest'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Plan</strong></td>
                                <td><?php echo $lastPlan ? esc_html($lastPlan) : '—'; ?></td>
                            </tr>
                            <?php if (is_array($me) && !empty($me['ok'])): ?>
                                <tr>
                                    <td><strong>Sites</strong></td>
                                    <td>
                                        <?php
                                        $sitesUsed = (int) ($me['plan']['usage']['sites'] ?? 0);
                                        $sitesLimit = (int) ($me['plan']['limits']['sites'] ?? 0);
                                        echo esc_html($sitesUsed . ' / ' . $sitesLimit);
                                        ?>
                                        <span class="description">(upgrade for more)</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Audits</strong></td>
                                    <td>
                                        <?php
                                        $scansUsed = (int) ($me['plan']['usage']['scans_this_month'] ?? 0);
                                        $scansLimit = (int) ($me['plan']['limits']['scans_per_month'] ?? 0);
                                        echo esc_html($scansUsed . ' / ' . $scansLimit);
                                        ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong>Website URL</strong></td>
                                <td><code><?php echo esc_html($websiteUrl); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>Site ID</strong></td>
                                <td><code><?php echo $sitePublicId ? esc_html($sitePublicId) : '—'; ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>Banner config</strong></td>
                                <td>
                                    <code><?php echo esc_html($localBannerVersion ? ('v' . (int) $localBannerVersion) : '—'); ?></code>
                                    <?php if ($connected && $autoSyncEnabled === '1'): ?>
                                        <span class="description">(auto-sync ON)</span>
                                    <?php elseif ($connected): ?>
                                        <span class="description">(auto-sync OFF)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Service</strong></td>
                                <td><code><?php echo esc_html($serviceUrl ?: '—'); ?></code></td>
                            </tr>
                            </tbody>
                        </table>

                        <h3 style="margin-top:14px;margin-bottom:6px;">What to do next</h3>
                        <ol class="sc-steps">
                            <li>Enable <strong>CMP snippet</strong> and <strong>Verification</strong> in the Banner tab.</li>
                            <li>Click <strong>Verify</strong> to confirm your site.</li>
                            <li>Click <strong>Check snippet</strong> to confirm the banner is detected.</li>
                        </ol>
                    </div>
                </div>

                <div class="sc-card" style="margin-top:16px;">
                    <h2>Quick actions</h2>
                    <div class="sc-actions">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('sc_sync_site'); ?>
                            <input type="hidden" name="action" value="sc_sync_site" />
                            <button type="submit" class="button" <?php disabled(!$connected); ?>>
                                Sync site <?php if (!$connected): ?><span class="description">(requires API key)</span><?php endif; ?>
                            </button>
                        </form>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('sc_check_banner'); ?>
                            <input type="hidden" name="action" value="sc_check_banner" />
                            <button type="submit" class="button" <?php disabled(!$connected); ?>>
                                Check snippet <?php if (!$connected): ?><span class="description">(requires API key)</span><?php endif; ?>
                            </button>
                        </form>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('sc_verify'); ?>
                            <input type="hidden" name="action" value="sc_verify" />
                            <button type="submit" class="button" <?php disabled(!$connected); ?>>
                                Verify <?php if (!$connected): ?><span class="description">(requires API key)</span><?php endif; ?>
                            </button>
                        </form>

                        <?php if ($dashboardSiteUrl): ?>
                            <a class="button" href="<?php echo esc_url($dashboardSiteUrl); ?>" target="_blank" rel="noreferrer">Open dashboard</a>
                        <?php endif; ?>

                        <?php if (!$apiKey && $registerUrl): ?>
                            <a class="button button-primary" href="<?php echo esc_url($registerUrl); ?>" target="_blank" rel="noreferrer">Create account</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'banner'): ?>
                <div class="sc-card" style="margin-top:14px;">
                    <h2>Install</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('sc_save_toggles'); ?>
                <input type="hidden" name="action" value="sc_save_toggles" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">CMP snippet</th>
                        <td>
                            <label>
                                <input type="checkbox" name="inject_banner" value="1" <?php checked($injectBanner, '1'); ?> />
                                Inject the CMP snippet into <code>&lt;head&gt;</code>
                            </label>
                            <p class="description">Best-effort: some themes/plugins may still load tags earlier.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Verification</th>
                        <td>
                            <label>
                                <input type="checkbox" name="inject_meta" value="1" <?php checked($injectMeta, '1'); ?> <?php disabled(!$connected); ?> />
                                Inject verification meta tag into <code>&lt;head&gt;</code>
                                <?php if (!$connected): ?><span class="description">(requires API key)</span><?php endif; ?>
                            </label>
                            <p class="description">Allows dashboard verification without editing theme files.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sync</th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_sync" value="1" <?php checked($autoSyncEnabled, '1'); ?> <?php disabled(!$connected); ?> />
                                Auto-sync settings and status from the dashboard
                                <?php if (!$connected): ?><span class="description">(requires API key)</span><?php endif; ?>
                            </label>
                            <p class="description">Keeps banner settings and verification status up-to-date without manual Pull/Sync.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save', 'primary', 'submit', true); ?>
            </form>

            <h2 class="title">Banner</h2>
            <p class="description">
                Configure the cookie banner here or in the dashboard. Use Pull/Save to sync settings (best-effort).
            </p>

            <div style="display:flex; gap: 8px; flex-wrap: wrap; margin: 10px 0;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_save_toggles'); ?>
                    <input type="hidden" name="action" value="sc_save_toggles" />
                    <input type="hidden" name="inject_banner" value="1" />
                    <input type="hidden" name="inject_meta" value="<?php echo esc_attr($injectMeta); ?>" />
                    <input type="hidden" name="auto_sync" value="<?php echo esc_attr($autoSyncEnabled); ?>" />
                    <button type="submit" class="button button-primary">
                        Enable banner
                    </button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_save_toggles'); ?>
                    <input type="hidden" name="action" value="sc_save_toggles" />
                    <input type="hidden" name="inject_banner" value="0" />
                    <input type="hidden" name="inject_meta" value="<?php echo esc_attr($injectMeta); ?>" />
                    <input type="hidden" name="auto_sync" value="<?php echo esc_attr($autoSyncEnabled); ?>" />
                    <button type="submit" class="button">
                        Disable banner
                    </button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_pull_banner_config'); ?>
                    <input type="hidden" name="action" value="sc_pull_banner_config" />
                    <button type="submit" class="button" <?php disabled(!$connected); ?>>
                        Pull from dashboard <?php if (!$connected): ?><span class="description">(requires API key)</span><?php endif; ?>
                    </button>
                </form>

                <span class="description" style="align-self:center;">
                    Synced version: <code><?php echo $localBannerVersion ? esc_html((string) $localBannerVersion) : '—'; ?></code>
                </span>
            </div>

            <form id="sc-banner-config-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('sc_save_banner_config'); ?>
                <input type="hidden" name="action" value="sc_save_banner_config" />

                <div class="sc-banner-grid">
                    <div>
                        <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="locale">Language</label></th>
                        <td>
                            <select name="locale" id="locale">
                                <option value="pl" <?php selected(($localBannerCfg['locale'] ?? 'pl'), 'pl'); ?>>PL</option>
                                <option value="en" <?php selected(($localBannerCfg['locale'] ?? 'pl'), 'en'); ?>>EN</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="regionMode">Region</label></th>
                        <td>
                            <select name="regionMode" id="regionMode">
                                <option value="auto" <?php selected(($localBannerCfg['regionMode'] ?? 'auto'), 'auto'); ?>>Auto</option>
                                <option value="eu" <?php selected(($localBannerCfg['regionMode'] ?? 'auto'), 'eu'); ?>>EU</option>
                                <option value="us" <?php selected(($localBannerCfg['regionMode'] ?? 'auto'), 'us'); ?>>US</option>
                            </select>
                            <p class="description">Controls which button labels and privacy options are shown (EU vs US). Auto uses best-effort detection.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="position">Position</label></th>
                        <td>
                            <select name="position" id="position">
                                <?php
                                $positions = ['bottom', 'bottom-left', 'bottom-right', 'top', 'top-left', 'top-right'];
                                foreach ($positions as $pos) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($pos),
                                        selected(($localBannerCfg['position'] ?? 'bottom'), $pos, false),
                                        esc_html($pos)
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bannerSize">Size</label></th>
                        <td>
                            <select name="bannerSize" id="bannerSize">
                                <option value="standard" <?php selected(($localBannerCfg['bannerSize'] ?? 'standard'), 'standard'); ?>>Standard</option>
                                <option value="compact" <?php selected(($localBannerCfg['bannerSize'] ?? 'standard'), 'compact'); ?>>Compact</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="style">Style</label></th>
                        <td>
                            <select name="style" id="style">
                                <option value="bar" <?php selected(($localBannerCfg['style'] ?? 'bar'), 'bar'); ?>>Bar</option>
                                <option value="rectangle-right" <?php selected(($localBannerCfg['style'] ?? 'bar'), 'rectangle-right'); ?>>Rectangle (right)</option>
                                <option value="rectangle-left" <?php selected(($localBannerCfg['style'] ?? 'bar'), 'rectangle-left'); ?>>Rectangle (left)</option>
                                <option value="elegant" <?php selected(($localBannerCfg['style'] ?? 'bar'), 'elegant'); ?>>Elegant</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="primaryColor">Primary color</label></th>
                        <td><input name="primaryColor" id="primaryColor" type="text" class="regular-text" value="<?php echo esc_attr((string) ($localBannerCfg['primaryColor'] ?? '#059669')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="backgroundColor">Background color</label></th>
                        <td><input name="backgroundColor" id="backgroundColor" type="text" class="regular-text" value="<?php echo esc_attr((string) ($localBannerCfg['backgroundColor'] ?? '#ffffff')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Dark mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="autoTheme" value="1"
                                    <?php checked(!array_key_exists('autoTheme', (array) $localBannerCfg) || !empty($localBannerCfg['autoTheme'])); ?>
                                    />
                                Auto adapt to dark mode
                            </label>
                            <p class="description">When enabled, the banner switches to a dark surface on dark-mode pages if your background color is the default white.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Buttons</th>
                        <td>
                            <label><input type="checkbox" name="showDeclineButton" value="1" <?php checked(!empty($localBannerCfg['showDeclineButton'])); ?> /> Show decline</label><br />
                            <label><input type="checkbox" name="showPreferencesButton" value="1" <?php checked(!empty($localBannerCfg['showPreferencesButton'])); ?> /> Show preferences</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Google IDs (optional)</th>
                        <td>
                            <p class="description">If you set IDs here, the CMP can gate common Google tags after consent (best-effort).</p>
                            <p><label for="gtmContainerId">GTM</label><br /><input name="gtmContainerId" id="gtmContainerId" type="text" class="regular-text" value="<?php echo esc_attr((string) ($localBannerCfg['google']['gtmContainerId'] ?? '')); ?>" /></p>
                            <p><label for="ga4MeasurementId">GA4</label><br /><input name="ga4MeasurementId" id="ga4MeasurementId" type="text" class="regular-text" value="<?php echo esc_attr((string) ($localBannerCfg['google']['ga4MeasurementId'] ?? '')); ?>" /></p>
                            <p><label for="googleAdsTagId">Google Ads</label><br /><input name="googleAdsTagId" id="googleAdsTagId" type="text" class="regular-text" value="<?php echo esc_attr((string) ($localBannerCfg['google']['googleAdsTagId'] ?? '')); ?>" /></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Auto-blocker</th>
                        <td>
                            <label><input type="checkbox" name="autoBlockEnabled" value="1" <?php checked(!empty($localBannerCfg['autoBlock']['enabled'])); ?> /> Enable attribute-based unlocking</label>
                            <p class="description">Best when the snippet is placed in <code>&lt;head&gt;</code> before trackers.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Consent telemetry</th>
                        <td>
                            <label><input type="checkbox" name="telemetryConsentLog" value="1" <?php checked(!empty($localBannerCfg['telemetry']['consentLog'])); ?> <?php disabled(!$connected); ?> /> Enable consent telemetry</label>
                            <p class="description">Anonymous choice signals visible in your dashboard.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Advanced (best-effort)</th>
                        <td>
                            <label><input type="checkbox" name="experimentalNetworkBlocker" value="1" <?php checked(!empty($localBannerCfg['experimental']['networkBlocker'])); ?> <?php disabled(!$connected); ?> /> Google tag blocker/gater (best-effort)</label>
                            <p class="description">OFF by default. Attempts to gate common Google tag loads until consent. Not retroactive and not guaranteed.</p>
                        </td>
                    </tr>
                        </table>
                    </div>

                    <div>
                        <h3 style="margin-top:0;">Banner preview</h3>
                        <?php
                        $previewBase = rtrim($this->get_service_url(), "/ \t\n\r\0\x0B") . '/preview/banner';
                        $previewSite = $sitePublicId !== '' ? $sitePublicId : 'preview';
                        ?>
                        <div id="sc-banner-preview" data-connected="<?php echo $connected ? '1' : '0'; ?>" data-theme="auto">
                        <div class="sc-preview-toolbar">
                            <button type="button" class="button" data-sc-preview-theme="auto">Auto</button>
                            <button type="button" class="button" data-sc-preview-theme="light">Light</button>
                            <button type="button" class="button" data-sc-preview-theme="dark">Dark</button>
                        </div>
                        <div class="sc-preview-viewport" aria-label="Banner preview">
                            <?php if ($connected): ?>
                            <iframe
                                id="sc-banner-preview-iframe"
                                title="Banner preview"
                                loading="lazy"
                                referrerpolicy="no-referrer"
                                sandbox="allow-scripts allow-forms allow-popups"
                                data-preview-base="<?php echo esc_attr($previewBase); ?>"
                                data-site="<?php echo esc_attr($previewSite); ?>"
                            ></iframe>
                            <?php else: ?>
                                <div id="sc-local-preview" class="sc-local-preview" aria-label="Local banner preview"></div>
                            <?php endif; ?>
                        </div>
                        <p class="description" style="margin-top:8px;">
                            <?php if ($connected): ?>
                                Preview uses the real banner renderer. Choices are not saved.
                            <?php else: ?>
                                Offline preview (no dashboard connection). Choices are not saved.
                            <?php endif; ?>
                        </p>
                        </div>
                    </div>
                </div>

                <div id="sc-banner-unsaved" class="sc-muted" style="margin:8px 0 0;"></div>
                <?php submit_button($connected ? 'Save to dashboard' : 'Save locally', 'primary', 'submit', true); ?>
            </form>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'audit'): ?>
                <div class="sc-card" style="margin-top:14px;">
                    <h2>Audit</h2>
            <p class="description">
                Run a light audit to see if your banner and policy links are detected, and get a practical checklist. Deep audit is available on Agency.
            </p>

            <div style="display:flex; gap: 8px; flex-wrap: wrap; margin: 10px 0;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_run_light_scan'); ?>
                    <input type="hidden" name="action" value="sc_run_light_scan" />
                    <button type="submit" class="button button-primary" <?php disabled(!$connected); ?>>
                        Run light audit <?php if (!$connected): ?><span class="description">(requires API key)</span><?php endif; ?>
                    </button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_run_deep_scan'); ?>
                    <input type="hidden" name="action" value="sc_run_deep_scan" />
                    <button type="submit" class="button" <?php disabled(!$connected || !$canDeep); ?>>
                        Run deep audit <span class="description">(Agency plan)</span>
                    </button>
                </form>

                <?php if ($auditUrl): ?>
                    <a class="button" href="<?php echo esc_url($auditUrl); ?>" target="_blank" rel="noreferrer">Run free audit <span class="description">(website)</span></a>
                <?php endif; ?>
            </div>

            <?php if (!$connected): ?>
                <p class="description">Connect with an API key to view audit results and recommendations inside WordPress.</p>
            <?php else: ?>
                <?php if (!is_array($latest) || empty($latest['ok'])): ?>
                    <p class="description">Latest audit: unavailable (try Sync site, then run a light audit).</p>
                <?php elseif (empty($latest['scan'])): ?>
                    <p class="description">No audits yet. Run a light audit to get your first checklist.</p>
                <?php else: ?>
                    <?php
                    $scan = is_array($latest['scan']) ? $latest['scan'] : [];
                    $checks = is_array($scan['checks'] ?? null) ? ($scan['checks'] ?? []) : [];
                    $recs = is_array($scan['recommendations'] ?? null) ? ($scan['recommendations'] ?? []) : [];
                    $dashScans = is_array($latest['links'] ?? null) ? (string) ($latest['links']['dashboard_scans'] ?? '') : '';

                    $status = (string) ($scan['status'] ?? '');
                    $type = (string) ($scan['scan_type'] ?? '');
                    ?>

                    <table class="widefat striped" style="margin-top: 10px;">
                        <tbody>
                        <tr>
                            <td style="width: 220px;"><strong>Latest audit</strong></td>
                            <td>
                                <code><?php echo esc_html($status ?: 'unknown'); ?></code>
                                <?php if ($type): ?><span class="description">(<?php echo esc_html($type); ?>)</span><?php endif; ?>
                                <?php if ($dashScans): ?>
                                    <a href="<?php echo esc_url($dashScans); ?>" target="_blank" rel="noreferrer" style="margin-left: 8px;">Open in dashboard</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <?php if (count($checks) > 0): ?>
                        <h3 style="margin-top: 14px; margin-bottom: 6px;">Checks</h3>
                        <ul style="margin: 0; padding-left: 18px; list-style: disc;">
                            <?php foreach (array_slice($checks, 0, 10) as $c): ?>
                                <?php
                                $label = is_array($c) ? (string) ($c['label'] ?? '') : '';
                                $st = is_array($c) ? (string) ($c['status'] ?? '') : '';
                                if ($label === '') {
                                    continue;
                                }
                                ?>
                                <li><?php echo esc_html($label); ?> <code><?php echo esc_html($st ?: ''); ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (count($recs) > 0): ?>
                        <h3 style="margin-top: 14px; margin-bottom: 6px;">Recommendations</h3>
                        <ol style="margin: 0; padding-left: 18px; list-style: decimal;">
                            <?php foreach (array_slice($recs, 0, 8) as $r): ?>
                                <?php
                                $text = is_array($r) ? (string) ($r['text'] ?? '') : '';
                                $sev = is_array($r) ? (string) ($r['severity'] ?? '') : '';
                                if ($text === '') {
                                    continue;
                                }
                                ?>
                                <li>
                                    <?php echo esc_html($text); ?>
                                    <?php if ($sev): ?> <span class="description">(<?php echo esc_html($sev); ?>)</span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

                </div>
            <?php endif; ?>

            <?php if ($tab === 'plans'): ?>
                <div class="sc-card" style="margin-top:14px;">
                    <h2>Plans</h2>
            <p class="description">
                Starter: more sites and more monthly audits. Agency: deep audit (headless) + more audits. Checkout happens in the dashboard (Stripe). In-plugin checkout will be added later.
            </p>
            <div style="display:flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
                <?php if ($pricingUrl): ?>
                    <a class="button" href="<?php echo esc_url($pricingUrl); ?>" target="_blank" rel="noreferrer">View plans</a>
                    <a class="button" href="<?php echo esc_url($pricingUrl . '#starter'); ?>" target="_blank" rel="noreferrer">Buy Starter <span class="description">(requires account)</span></a>
                    <a class="button" href="<?php echo esc_url($pricingUrl . '#agency'); ?>" target="_blank" rel="noreferrer">Buy Agency <span class="description">(requires account)</span></a>
                <?php endif; ?>
                <?php if ($billingUrl): ?>
                    <a class="button button-primary" href="<?php echo esc_url($billingUrl); ?>" target="_blank" rel="noreferrer">Open billing</a>
                <?php endif; ?>
            </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

register_activation_hook(__FILE__, [SaaS_Cookie_CMP_Plugin::class, 'activate']);
SaaS_Cookie_CMP_Plugin::init();

