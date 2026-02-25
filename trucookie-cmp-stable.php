<?php
/**
 * Plugin Name: TruCookie CMP Stable
 * Plugin URI: https://trucookie.pro
 * Description: Stable cookie banner for WordPress with TruCookie-compatible connected mode and consent API logging.
 * Version: 0.4.4
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: TruCookie
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: trucookie-cmp-stable
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TCS_VERSION', '0.4.4');
define('TCS_PLUGIN_FILE', __FILE__);
define('TCS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TCS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once TCS_PLUGIN_DIR . 'includes/Core/Settings.php';
require_once TCS_PLUGIN_DIR . 'includes/Api/ConsentLogger.php';
require_once TCS_PLUGIN_DIR . 'includes/Frontend/Frontend.php';
require_once TCS_PLUGIN_DIR . 'includes/Admin/Modules/Onboarding.php';
require_once TCS_PLUGIN_DIR . 'includes/Admin/Admin.php';
require_once TCS_PLUGIN_DIR . 'includes/Core/Plugin.php';

register_activation_hook(TCS_PLUGIN_FILE, ['TruCookieCMP\\Core\\Plugin', 'activate']);

add_filter('wp_consent_api_registered_' . plugin_basename(TCS_PLUGIN_FILE), '__return_true');

add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain(
        'trucookie-cmp-stable',
        false,
        dirname(plugin_basename(TCS_PLUGIN_FILE)) . '/languages'
    );
}, 0);

add_action('plugins_loaded', static function (): void {
    TruCookieCMP\Core\Plugin::boot();
}, 1);

