<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$warnings = [];

function fail(array &$failures, string $message): void
{
    $failures[] = $message;
}

function warn(array &$warnings, string $message): void
{
    $warnings[] = $message;
}

$requiredFiles = [
    'trucookie-cmp-stable.php',
    'readme.txt',
    'uninstall.php',
    'includes/Api/ConsentLogger.php',
    'includes/Core/Settings.php',
    'assets/js/banner.js',
    'assets/css/banner.css',
];

foreach ($requiredFiles as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        fail($failures, "Missing required file: {$relative}");
    }
}

$pluginMainPath = $root . DIRECTORY_SEPARATOR . 'trucookie-cmp-stable.php';
$readmePath = $root . DIRECTORY_SEPARATOR . 'readme.txt';

$pluginMain = is_file($pluginMainPath) ? (string) file_get_contents($pluginMainPath) : '';
$readme = is_file($readmePath) ? (string) file_get_contents($readmePath) : '';

if (!preg_match('/^\s*\*\s*Version:\s*([0-9.]+)/mi', $pluginMain, $pluginMatch)) {
    fail($failures, 'Plugin main header Version not found.');
    $pluginVersion = '';
} else {
    $pluginVersion = trim((string) $pluginMatch[1]);
}

if (!preg_match('/^Stable tag:\s*([0-9.]+)/mi', $readme, $readmeMatch)) {
    fail($failures, 'readme.txt Stable tag not found.');
    $stableTag = '';
} else {
    $stableTag = trim((string) $readmeMatch[1]);
}

if ($pluginVersion !== '' && $stableTag !== '' && $pluginVersion !== $stableTag) {
    fail($failures, "Version mismatch: plugin={$pluginVersion}, stable_tag={$stableTag}");
}

if (strpos($pluginMain, 'load_plugin_textdomain(') === false) {
    fail($failures, 'Missing load_plugin_textdomain() in bootstrap.');
}

$consentLoggerPath = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'ConsentLogger.php';
$consentLogger = is_file($consentLoggerPath) ? (string) file_get_contents($consentLoggerPath) : '';
if (strpos($consentLogger, "permission_callback' => '__return_true'") !== false) {
    fail($failures, 'Consent endpoint is public (__return_true).');
}
if (strpos($consentLogger, 'is_rate_limited') === false) {
    warn($warnings, 'No obvious rate limiting found in ConsentLogger.');
}

$settingsPath = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Settings.php';
$settings = is_file($settingsPath) ? (string) file_get_contents($settingsPath) : '';
if (strpos($settings, "'forward_consent_logs'") === false) {
    fail($failures, 'Missing forward_consent_logs setting.');
}
if (strpos($settings, "'collect_user_metadata'") === false) {
    fail($failures, 'Missing collect_user_metadata setting.');
}

$wporgAssetFiles = [
    'wporg/assets/banner-1544x500.png',
    'wporg/assets/banner-772x250.png',
    'wporg/assets/icon-256x256.png',
    'wporg/assets/icon-128x128.png',
    'wporg/assets/screenshot-1.png',
];
foreach ($wporgAssetFiles as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        warn($warnings, "Missing WP.org listing asset copy: {$relative}");
    }
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    /** @var SplFileInfo $fileInfo */
    if ($fileInfo->isDir()) {
        continue;
    }
    if (strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    $cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
    exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
        fail($failures, 'PHP lint failed: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $path));
    }
}

if (!empty($warnings)) {
    echo "WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo " - {$warning}\n";
    }
}

if (!empty($failures)) {
    echo "FAILURES:\n";
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}

echo "OK: release check passed.\n";
exit(0);
