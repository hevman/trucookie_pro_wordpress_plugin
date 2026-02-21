<?php

$pluginMain = dirname(__DIR__) . '/trucookie-cmp.php';
$adminPageTrait = dirname(__DIR__) . '/includes/Traits/AdminPageTrait.php';

if (!file_exists($pluginMain)) {
    fwrite(STDERR, "Missing plugin main file: $pluginMain\n");
    exit(1);
}

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/_wp_stub');
}

$GLOBALS['sc_test_hooks'] = [];
$GLOBALS['sc_test_activation_hooks'] = [];
$GLOBALS['sc_test_options'] = [];

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['sc_test_hooks'][] = [
        'hook' => (string) $hook,
        'callback' => $callback,
        'priority' => (int) $priority,
        'accepted_args' => (int) $accepted_args,
    ];
    return true;
}

function register_activation_hook($file, $callback) {
    $GLOBALS['sc_test_activation_hooks'][] = [
        'file' => (string) $file,
        'callback' => $callback,
    ];
    return true;
}

function apply_filters($hook_name, $value) {
    return $value;
}

function wp_parse_url($url) {
    return parse_url((string) $url);
}

function sanitize_key($key) {
    $key = strtolower((string) $key);
    return preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '';
}

function esc_url_raw($url) {
    return filter_var((string) $url, FILTER_SANITIZE_URL) ?: '';
}

function admin_url($path = '') {
    $base = 'https://wp.local/wp-admin/';
    $path = ltrim((string) $path, '/');
    return $base . $path;
}

function add_query_arg($args, $url) {
    $url = (string) $url;
    $query = http_build_query((array) $args);
    if ($query === '') {
        return $url;
    }
    $sep = (strpos($url, '?') === false) ? '?' : '&';
    return $url . $sep . $query;
}

function wp_nonce_url($url, $action = -1) {
    return add_query_arg([
        '_wpnonce' => 'test-nonce',
        '_wpaction' => (string) $action,
    ], (string) $url);
}

function get_option($name, $default = false) {
    $key = (string) $name;
    return array_key_exists($key, $GLOBALS['sc_test_options']) ? $GLOBALS['sc_test_options'][$key] : $default;
}

function update_option($name, $value) {
    $GLOBALS['sc_test_options'][(string) $name] = $value;
    return true;
}

function delete_option($name) {
    unset($GLOBALS['sc_test_options'][(string) $name]);
    return true;
}

function wp_salt($scheme = 'auth') {
    return 'stub-salt-' . (string) $scheme;
}

function home_url($path = '') {
    return 'https://example.test' . (string) $path;
}

function wp_json_encode($value) {
    return json_encode($value, JSON_UNESCAPED_SLASHES);
}

function __($text, $domain = null) {
    return (string) $text;
}

function assert_true($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] $message\n");
        exit(1);
    }
}

require $pluginMain;

assert_true(class_exists('SaaS_Cookie_CMP_Plugin'), 'Plugin class should be loadable.');

$requiredHooks = [
    'admin_post_sc_connect',
    'admin_post_sc_sync_site',
    'admin_post_sc_run_light_scan',
    'admin_post_sc_run_deep_scan',
    'admin_post_sc_track_upgrade_intent',
    'wp_head',
];

$registeredHooks = array_map(static function (array $row): string {
    return (string) $row['hook'];
}, $GLOBALS['sc_test_hooks']);

foreach ($requiredHooks as $hook) {
    assert_true(in_array($hook, $registeredHooks, true), "Expected hook '$hook' to be registered.");
}

assert_true(count($GLOBALS['sc_test_activation_hooks']) > 0, 'Activation hook should be registered.');

$reflection = new ReflectionClass('SaaS_Cookie_CMP_Plugin');
$instance = $reflection->newInstanceWithoutConstructor();

$normalize = $reflection->getMethod('normalize_base_url');
$normalized = (string) $normalize->invoke($instance, 'https://cmp.markets/audit');
assert_true($normalized === 'https://trucookie.pro/audit', 'Legacy cmp.markets URL should normalize to trucookie.pro.');

$tracked = $reflection->getMethod('tracked_upgrade_url');
$trackedUrl = (string) $tracked->invoke($instance, 'https://trucookie.pro/pricing#agency', 'plans_open_billing');
assert_true(strpos($trackedUrl, 'action=sc_track_upgrade_intent') !== false, 'Tracked URL should point to upgrade tracking action.');
assert_true(strpos($trackedUrl, 'intent=plans_open_billing') !== false, 'Tracked URL should include sanitized intent.');
assert_true(strpos($trackedUrl, 'target=https%3A%2F%2Ftrucookie.pro%2Fpricing%23agency') !== false, 'Tracked URL should carry encoded target URL.');

assert_true(file_exists($adminPageTrait), 'AdminPage trait file should exist.');
$adminPageSource = (string) file_get_contents($adminPageTrait);
assert_true(strpos($adminPageSource, 'audit_limit_upgrade') !== false, 'Audit tab should include upgrade intent CTA.');
assert_true(strpos($adminPageSource, 'deep_audit_unlock') !== false, 'Audit tab should include deep-audit unlock CTA.');
assert_true(strpos($adminPageSource, 'plans_open_billing') !== false, 'Plans tab should include billing CTA tracking.');
assert_true(strpos($adminPageSource, 'Run free audit') === false, 'Guest free-audit bypass CTA should not be present.');

echo "Integration flow checks passed.\n";
