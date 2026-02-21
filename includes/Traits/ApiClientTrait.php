<?php

trait SC_ApiClientTrait
{
    private function api_request(string $method, string $path, ?array $body = null): array
    {
        $base = $this->get_service_url();
        if ($base === '') {
            return ['ok' => false, 'error' => 'missing_service_url'];
        }

        $apiKey = $this->get_api_key();
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
        $this->set_api_key($apiKey);

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
        $apiKey = $this->get_api_key();
        $sitePublicId = (string) get_option(self::OPT_SITE_PUBLIC_ID, '');

        return $base !== '' && $apiKey !== '' && $sitePublicId !== '';
    }

}
