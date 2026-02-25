<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete plugin options and rate-limit transients for current site.
 */
function tcs_uninstall_delete_site_data(): void
{
    delete_option('tcs_settings');
    delete_option('tcs_consent_logs');

    global $wpdb;
    if (!isset($wpdb) || !($wpdb instanceof wpdb)) {
        return;
    }

    $transientPrefix = $wpdb->esc_like('_transient_tcs_consent_rl_') . '%';
    $timeoutPrefix = $wpdb->esc_like('_transient_timeout_tcs_consent_rl_') . '%';

    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $transientPrefix));
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $timeoutPrefix));
}

if (is_multisite()) {
    $siteIds = get_sites(['fields' => 'ids']);
    if (is_array($siteIds)) {
        foreach ($siteIds as $siteId) {
            switch_to_blog((int) $siteId);
            tcs_uninstall_delete_site_data();
            restore_current_blog();
        }
    }
} else {
    tcs_uninstall_delete_site_data();
}

