<?php
/**
 * Plugin Name: TruCookie CMP Stable
 * Plugin URI: https://trucookie.pro
 * Description: Stable cookie banner for WordPress with TruCookie-compatible connected mode and consent API logging.
 * Version: 0.4.8
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: TruCookie
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: trucookie-cmp-consent-mode-v2
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('TRUCOOKIE_CMP_VERSION')) {
    define('TRUCOOKIE_CMP_VERSION', '0.4.8');
}
if (!defined('TRUCOOKIE_CMP_PLUGIN_FILE')) {
    define('TRUCOOKIE_CMP_PLUGIN_FILE', __FILE__);
}
if (!defined('TRUCOOKIE_CMP_PLUGIN_DIR')) {
    define('TRUCOOKIE_CMP_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('TRUCOOKIE_CMP_PLUGIN_URL')) {
    define('TRUCOOKIE_CMP_PLUGIN_URL', plugin_dir_url(__FILE__));
}

require_once TRUCOOKIE_CMP_PLUGIN_DIR . 'includes/Core/Settings.php';
require_once TRUCOOKIE_CMP_PLUGIN_DIR . 'includes/Core/PlanSync.php';
require_once TRUCOOKIE_CMP_PLUGIN_DIR . 'includes/Api/ConsentLogger.php';
require_once TRUCOOKIE_CMP_PLUGIN_DIR . 'includes/Frontend/Frontend.php';
require_once TRUCOOKIE_CMP_PLUGIN_DIR . 'includes/Admin/Modules/Onboarding.php';
require_once TRUCOOKIE_CMP_PLUGIN_DIR . 'includes/Admin/Admin.php';
require_once TRUCOOKIE_CMP_PLUGIN_DIR . 'includes/Core/Plugin.php';

register_activation_hook(TRUCOOKIE_CMP_PLUGIN_FILE, ['TruCookieCMP\\Core\\Plugin', 'activate']);

add_filter('wp_consent_api_registered_' . plugin_basename(TRUCOOKIE_CMP_PLUGIN_FILE), '__return_true');

add_action('plugins_loaded', static function (): void {
    TruCookieCMP\Core\Plugin::boot();
}, 1);
