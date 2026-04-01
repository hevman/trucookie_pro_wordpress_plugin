<?php

namespace TruCookieCMP\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class PlanSync
{
    private const CACHE_TTL = 300;
    private const ERROR_CACHE_TTL = 60;

    /**
     * @return array{
     *   name:string,
     *   limits:array{sites:int,scans_per_month:int},
     *   usage:array{sites:int,scans_this_month:int},
     *   can_run_deep_audit:bool,
     *   source:string,
     *   message:string,
     *   synced_at:string
     * }
     */
    public function default_snapshot(): array
    {
        return [
            'name' => 'free',
            'limits' => ['sites' => 1, 'scans_per_month' => 5],
            'usage' => ['sites' => 0, 'scans_this_month' => 0],
            'can_run_deep_audit' => false,
            'source' => 'local',
            'message' => '',
            'synced_at' => '',
        ];
    }

    /**
     * @param array<string,string> $settings
     * @return array{
     *   name:string,
     *   limits:array{sites:int,scans_per_month:int},
     *   usage:array{sites:int,scans_this_month:int},
     *   can_run_deep_audit:bool,
     *   source:string,
     *   message:string,
     *   synced_at:string
     * }
     */
    public function resolve(array $settings, bool $force_refresh = false): array
    {
        $snapshot = $this->default_snapshot();

        $service_url = isset($settings['service_url']) ? rtrim((string) $settings['service_url'], '/') : '';
        $api_key = isset($settings['api_key']) ? trim((string) $settings['api_key']) : '';
        if ($service_url === '' || $api_key === '') {
            $snapshot['message'] = 'Set Service URL and API key to sync your plan.';
            return $snapshot;
        }

        $cache_key = $this->build_cache_key($service_url, $api_key);
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return array_merge($snapshot, $cached);
            }
        }

        $response = wp_remote_get($service_url . '/api/plugin/me', [
            'timeout' => 4,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
        ]);

        if (is_wp_error($response)) {
            $snapshot['source'] = 'error';
            $snapshot['message'] = $response->get_error_message();
            set_transient($cache_key, $snapshot, self::ERROR_CACHE_TTL);
            return $snapshot;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            $snapshot['source'] = 'error';
            $snapshot['message'] = 'Plan sync failed with HTTP ' . $status . '.';
            set_transient($cache_key, $snapshot, self::ERROR_CACHE_TTL);
            return $snapshot;
        }

        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['plan']) || !is_array($data['plan'])) {
            $snapshot['source'] = 'error';
            $snapshot['message'] = 'Plan sync returned invalid payload.';
            set_transient($cache_key, $snapshot, self::ERROR_CACHE_TTL);
            return $snapshot;
        }

        $plan = $data['plan'];
        $name = $this->normalize_plan_name($plan['name'] ?? null);
        $limits = is_array($plan['limits'] ?? null) ? $plan['limits'] : [];
        $usage = is_array($plan['usage'] ?? null) ? $plan['usage'] : [];

        $resolved = [
            'name' => $name,
            'limits' => [
                'sites' => max(0, (int) ($limits['sites'] ?? $snapshot['limits']['sites'])),
                'scans_per_month' => max(0, (int) ($limits['scans_per_month'] ?? $snapshot['limits']['scans_per_month'])),
            ],
            'usage' => [
                'sites' => max(0, (int) ($usage['sites'] ?? $snapshot['usage']['sites'])),
                'scans_this_month' => max(0, (int) ($usage['scans_this_month'] ?? $snapshot['usage']['scans_this_month'])),
            ],
            'can_run_deep_audit' => !empty($plan['can_run_deep_audit']),
            'source' => 'remote',
            'message' => 'Plan synced from TruCookie API.',
            'synced_at' => gmdate('c'),
        ];

        set_transient($cache_key, $resolved, self::CACHE_TTL);

        return $resolved;
    }

    /**
     * @param array<string,string> $settings
     * @return array{
     *   settings:array<string,string>,
     *   snapshot:array{
     *     name:string,
     *     limits:array{sites:int,scans_per_month:int},
     *     usage:array{sites:int,scans_this_month:int},
     *     can_run_deep_audit:bool,
     *     source:string,
     *     message:string,
     *     synced_at:string
     *   },
     *   changed:bool
     * }
     */
    public function enforce_settings(array $settings): array
    {
        $resolved = $this->resolve($settings, true);
        $changed = false;

        if (($resolved['name'] ?? 'free') === 'free') {
            if (($settings['mode'] ?? 'auto') === 'connected') {
                $settings['mode'] = 'local';
                $changed = true;
            }
            if (($settings['remote_banner_url'] ?? '') !== '') {
                $settings['remote_banner_url'] = '';
                $changed = true;
            }
            if (($settings['forward_consent_logs'] ?? '0') === '1') {
                $settings['forward_consent_logs'] = '0';
                $changed = true;
            }
        }

        return [
            'settings' => $settings,
            'snapshot' => $resolved,
            'changed' => $changed,
        ];
    }

    private function build_cache_key(string $service_url, string $api_key): string
    {
        return 'tcs_plan_' . md5($service_url . '|' . $api_key);
    }

    /**
     * @param mixed $name
     */
    private function normalize_plan_name($name): string
    {
        $plan = sanitize_key((string) $name);
        if ($plan === '') {
            return 'free';
        }

        return $plan;
    }
}
