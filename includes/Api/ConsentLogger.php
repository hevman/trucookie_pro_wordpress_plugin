<?php

namespace TruCookieCMP\Api;

use TruCookieCMP\Core\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ConsentLogger
{
    /** @var Settings */
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('trucookie-cmp/v1', '/consent', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'log_consent'],
            'permission_callback' => [$this, 'permission_check'],
        ]);
    }

    /**
     * @return true|WP_Error
     */
    public function permission_check(WP_REST_Request $request)
    {
        if (strtoupper((string) $request->get_method()) !== 'POST') {
            return new WP_Error('tcs_method_not_allowed', 'Method not allowed.', ['status' => 405]);
        }

        if (!$this->is_same_site_request($request)) {
            return new WP_Error('tcs_forbidden_origin', 'Forbidden origin.', ['status' => 403]);
        }

        if ($this->is_rate_limited()) {
            return new WP_Error('tcs_rate_limited', 'Too many requests.', ['status' => 429]);
        }

        return true;
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function log_consent(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return new WP_Error('tcs_invalid_body', 'Invalid payload.', ['status' => 400]);
        }

        $consent = $this->normalize_consent($payload['consent'] ?? []);
        $event = [
            'site_url' => esc_url_raw((string) home_url('/')),
            'url' => isset($payload['url']) ? esc_url_raw((string) $payload['url']) : esc_url_raw((string) home_url('/')),
            'referrer' => isset($payload['referrer']) ? esc_url_raw((string) $payload['referrer']) : '',
            'consent' => $consent,
            'source' => 'wordpress-plugin',
            'plugin_version' => defined('TCS_VERSION') ? (string) TCS_VERSION : 'unknown',
            'created_at' => gmdate('c'),
        ];

        if ($this->settings->is_user_metadata_collection_enabled()) {
            $event['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 512) : '';
            $event['ip_hint'] = $this->get_client_ip_hint();
        }

        $this->store_local_event($event);

        $forwarded = false;
        $remoteStatus = 0;
        $remoteBody = '';
        $remoteError = '';

        if ($this->can_forward_to_trucookie()) {
            $remote = $this->forward_to_trucookie($event);
            $forwarded = (bool) ($remote['ok'] ?? false);
            $remoteStatus = (int) ($remote['status'] ?? 0);
            $remoteBody = (string) ($remote['body'] ?? '');
            $remoteError = (string) ($remote['error'] ?? '');
        }

        return new WP_REST_Response([
            'ok' => true,
            'forwarded' => $forwarded,
            'remote_status' => $remoteStatus,
            'remote_error' => $remoteError,
            'remote_body' => $this->settings->get('debug') === '1' ? $remoteBody : '',
        ], 200);
    }

    /**
     * @param mixed $consent
     * @return array<string,mixed>
     */
    private function normalize_consent($consent): array
    {
        $consent = is_array($consent) ? $consent : [];
        $analytics = !empty($consent['analytics']);
        $marketing = !empty($consent['marketing']);

        return [
            'necessary' => true,
            'analytics' => $analytics,
            'marketing' => $marketing,
            'schema_version' => 1,
            'timestamp' => isset($consent['ts']) ? (int) $consent['ts'] : (int) (microtime(true) * 1000),
        ];
    }

    private function can_forward_to_trucookie(): bool
    {
        return $this->settings->is_consent_log_forwarding_enabled()
            && $this->settings->has_api_credentials()
            && $this->settings->get('log_path') !== '';
    }

    /**
     * @param array<string,mixed> $event
     * @return array<string,mixed>
     */
    private function forward_to_trucookie(array $event): array
    {
        $url = rtrim($this->settings->get('service_url'), '/') . '/' . ltrim($this->settings->get('log_path'), '/');
        $apiKey = $this->settings->get('api_key');
        $timeoutMs = (int) $this->settings->get('remote_timeout_ms');
        if ($timeoutMs < 1000) {
            $timeoutMs = 1000;
        }
        if ($timeoutMs > 10000) {
            $timeoutMs = 10000;
        }

        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'X-TruCookie-Integration' => 'wordpress-plugin',
            'X-TruCookie-Site-Url' => esc_url_raw((string) home_url('/')),
        ];
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $response = wp_remote_post($url, [
            'timeout' => ((float) $timeoutMs) / 1000,
            'redirection' => 1,
            'headers' => $headers,
            'body' => wp_json_encode($event),
        ]);

        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => '',
                'error' => $response->get_error_message(),
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $body,
            'error' => '',
        ];
    }

    /**
     * @param array<string,mixed> $event
     */
    private function store_local_event(array $event): void
    {
        $logs = get_option(Settings::LOG_OPTION_KEY, []);
        if (!is_array($logs)) {
            $logs = [];
        }

        array_unshift($logs, $event);
        if (count($logs) > 50) {
            $logs = array_slice($logs, 0, 50);
        }

        update_option(Settings::LOG_OPTION_KEY, $logs);
    }

    private function is_same_site_request(WP_REST_Request $request): bool
    {
        $siteHost = $this->normalize_host((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        if ($siteHost === '') {
            return false;
        }

        $origin = trim((string) $request->get_header('origin'));
        $referer = trim((string) $request->get_header('referer'));
        if ($origin === '' && $referer === '') {
            return false;
        }

        if ($origin !== '' && $this->normalize_host((string) wp_parse_url($origin, PHP_URL_HOST)) === $siteHost) {
            return true;
        }

        if ($referer !== '' && $this->normalize_host((string) wp_parse_url($referer, PHP_URL_HOST)) === $siteHost) {
            return true;
        }

        return false;
    }

    private function normalize_host(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }

        return preg_replace('/^www\./', '', $host);
    }

    private function get_client_ip_hint(): string
    {
        $candidates = [
            isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? (string) $_SERVER['HTTP_CF_CONNECTING_IP'] : '',
            isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? (string) $_SERVER['HTTP_X_FORWARDED_FOR'] : '',
            isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '',
        ];

        foreach ($candidates as $candidate) {
            $parts = explode(',', $candidate);
            foreach ($parts as $part) {
                $ip = trim($part);
                if ($ip === '') {
                    continue;
                }
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return substr($ip, 0, 64);
                }
            }
        }

        return '';
    }

    private function is_rate_limited(): bool
    {
        $ip = $this->get_client_ip_hint();
        if ($ip === '') {
            $ip = 'unknown';
        }

        $key = 'tcs_consent_rl_' . md5($ip);
        $count = (int) get_transient($key);
        $count++;
        set_transient($key, $count, 300);

        return $count > 120;
    }
}
