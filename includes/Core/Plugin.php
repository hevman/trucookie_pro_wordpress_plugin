<?php

namespace TruCookieCMP\Core;

use TruCookieCMP\Admin\Admin;
use TruCookieCMP\Admin\Modules\Onboarding;
use TruCookieCMP\Api\ConsentLogger;
use TruCookieCMP\Frontend\Frontend;

final class Plugin
{
    /** @var self|null */
    private static $instance = null;

    /** @var Settings */
    private $settings;

    /** @var ConsentLogger */
    private $logger;

    /** @var Frontend */
    private $frontend;

    /** @var Admin|null */
    private $admin = null;

    /** @var Onboarding|null */
    private $onboarding = null;

    public static function activate(): void
    {
        Settings::ensure_defaults();
        Onboarding::mark_for_redirect();
    }

    public static function boot(): void
    {
        if (self::$instance !== null) {
            return;
        }

        self::$instance = new self();
        self::$instance->init();
    }

    private function init(): void
    {
        $this->settings = new Settings();
        $this->logger = new ConsentLogger($this->settings);
        $this->frontend = new Frontend($this->settings);

        if (is_admin()) {
            $this->onboarding = new Onboarding();
            $this->admin = new Admin($this->settings);
        }
    }
}
