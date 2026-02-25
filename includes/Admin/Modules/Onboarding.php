<?php

namespace TruCookieCMP\Admin\Modules;

final class Onboarding
{
    public const REDIRECT_OPTION_KEY = 'tcs_do_activation_redirect';

    public function __construct()
    {
        add_action('admin_init', [$this, 'maybe_redirect_after_activation']);
    }

    public static function mark_for_redirect(): void
    {
        if (is_multisite() && is_network_admin()) {
            return;
        }
        add_option(self::REDIRECT_OPTION_KEY, '1');
    }

    public function maybe_redirect_after_activation(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (wp_doing_ajax()) {
            return;
        }
        if (get_option(self::REDIRECT_OPTION_KEY, '0') !== '1') {
            return;
        }

        delete_option(self::REDIRECT_OPTION_KEY);
        wp_safe_redirect(admin_url('admin.php?page=trucookie-cmp-stable&onboarding=1'));
        exit;
    }
}

