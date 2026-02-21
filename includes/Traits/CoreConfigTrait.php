<?php

trait SC_CoreConfigTrait
{
    private function normalize_base_url(string $url): string
    {
        $url = trim($url);
        $url = rtrim($url, "/ \t\n\r\0\x0B");
        if ($url === '') {
            return '';
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }

        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        if ($host !== '' && in_array($host, self::LEGACY_SERVICE_HOSTS, true)) {
            $path = isset($parts['path']) ? trim((string) $parts['path']) : '';
            $path = trim($path, "/ \t\n\r\0\x0B");
            return self::DEFAULT_SERVICE_URL . ($path !== '' ? '/' . $path : '');
        }

        return $url;
    }

    private function sanitize_choice(string $value, array $allowed, string $default): string
    {
        $v = trim($value);
        return in_array($v, $allowed, true) ? $v : $default;
    }

    private function sanitize_hex_color_or_default(string $value, string $default): string
    {
        $v = function_exists('sanitize_hex_color') ? sanitize_hex_color($value) : null;
        if (is_string($v) && $v !== '') {
            return strtolower($v);
        }
        return $default;
    }

    private function sanitize_tracking_id(?string $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9\-_]{3,64}$/', $v)) {
            return null;
        }
        return $v;
    }

    private function sanitize_api_key(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }

        // Keep only visible ASCII to avoid control/unicode injection in stored secret.
        $v = preg_replace('/[^\x21-\x7E]/', '', $v);
        $v = is_string($v) ? $v : '';
        if ($v === '') {
            return '';
        }

        return substr($v, 0, 512);
    }

    private function encrypt_api_key(string $plain): string
    {
        $plain = trim($plain);
        if ($plain === '') {
            return '';
        }
        if (!function_exists('openssl_encrypt')) {
            return $plain;
        }

        $keyMaterial = (string) wp_salt('auth');
        if ($keyMaterial === '') {
            return $plain;
        }

        $key = hash('sha256', $keyMaterial, true);
        $iv = substr(hash('sha256', (string) wp_salt('nonce'), true), 0, 16);
        $enc = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if (!is_string($enc) || $enc === '') {
            return $plain;
        }

        return 'enc:v1:' . base64_encode($enc);
    }

    private function decrypt_api_key(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }
        if (strpos($stored, 'enc:v1:') !== 0) {
            return $stored;
        }
        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        $payload = substr($stored, 7);
        $raw = base64_decode($payload, true);
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        $keyMaterial = (string) wp_salt('auth');
        if ($keyMaterial === '') {
            return '';
        }

        $key = hash('sha256', $keyMaterial, true);
        $iv = substr(hash('sha256', (string) wp_salt('nonce'), true), 0, 16);
        $plain = openssl_decrypt($raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return is_string($plain) ? trim($plain) : '';
    }

    private function get_api_key(): string
    {
        $raw = (string) get_option(self::OPT_API_KEY, '');
        if ($raw === '') {
            return '';
        }

        $plain = $this->decrypt_api_key($raw);
        if ($plain === '') {
            return '';
        }

        // Migrate legacy plaintext storage to encrypted storage.
        if (strpos($raw, 'enc:v1:') !== 0) {
            $this->set_api_key($plain);
        }

        return $plain;
    }

    private function set_api_key(string $apiKey): void
    {
        $apiKey = $this->sanitize_api_key($apiKey);
        if ($apiKey === '') {
            delete_option(self::OPT_API_KEY);
            return;
        }
        update_option(self::OPT_API_KEY, $this->encrypt_api_key($apiKey));
    }

    private function tracked_upgrade_url(string $targetUrl, string $intent): string
    {
        $u = admin_url('admin-post.php');
        $args = [
            'action' => 'sc_track_upgrade_intent',
            'intent' => sanitize_key($intent),
            'target' => $targetUrl,
        ];
        $u = add_query_arg($args, $u);
        return wp_nonce_url($u, 'sc_track_upgrade_intent');
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
        $rawOpt = (string) get_option(self::OPT_SERVICE_URL, '');
        $opt = $this->normalize_base_url($rawOpt);
        $trimmedRawOpt = rtrim(trim($rawOpt), "/ \t\n\r\0\x0B");
        if ($opt !== '' && $opt !== $trimmedRawOpt) {
            update_option(self::OPT_SERVICE_URL, $opt);
        }
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
        return strpos($wpLocale, 'pl') === 0 ? 'pl' : 'en';
    }

}
