<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete plugin options and rate-limit transients for current site.
 */
function trucookie_cmp_consent_mode_v2_uninstall_delete_site_data(): void
{
    delete_option('tcs_settings');
    delete_option('tcs_consent_logs');
}

if (is_multisite()) {
    $trucookie_cmp_consent_mode_v2_site_ids = get_sites(['fields' => 'ids']);
    if (is_array($trucookie_cmp_consent_mode_v2_site_ids)) {
        foreach ($trucookie_cmp_consent_mode_v2_site_ids as $trucookie_cmp_consent_mode_v2_site_id) {
            switch_to_blog((int) $trucookie_cmp_consent_mode_v2_site_id);
            trucookie_cmp_consent_mode_v2_uninstall_delete_site_data();
            restore_current_blog();
        }
    }
} else {
    trucookie_cmp_consent_mode_v2_uninstall_delete_site_data();
}

