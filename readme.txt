=== TruCookie CMP (Consent Mode v2) ===
Contributors: trucookie
Tags: gdpr, cookie consent, google consent mode, consent mode v2, cookie banner
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Tested up to PHP: 8.5
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GDPR cookie consent for WordPress with Google Consent Mode v2, CMP banner controls, verification, and privacy audit workflows.

== Description ==

TruCookie CMP helps you run a cookie banner and Google Consent Mode v2 setup from one WordPress panel.

Main capabilities:
* WordPress CMP panel for banner controls and privacy settings
* Google Consent Mode v2 controls
* Optional verification meta tag injection
* Light/deep audit actions and recommendations (when connected)
* Plan and billing shortcuts from WordPress admin
* Built-in translations for major WordPress locales (en_US, pl_PL, de_DE, es_ES, fr_FR, it_IT, pt_BR)

Connected mode:
* Syncs your site with the dashboard
* (Optional) injects the CMP snippet into <head>
* (Optional) injects verification meta tag into <head>
* Triggers best-effort verification in the dashboard

== Third-party services ==

This plugin connects to the TruCookie SaaS service.

Service provider:
* TruCookie (https://trucookie.pro)

What is sent:
* Website URL/host used for site connect/sync and verification
* API key in authenticated requests
* Site/public IDs and scan request metadata required for audit operations

When data is sent:
* When an admin connects/syncs/verifies/runs audits in plugin settings
* When connected mode banner snippet is enabled, front-end loads banner script from TruCookie domain

Service links:
* Privacy Policy: https://trucookie.pro/privacy
* Terms: https://trucookie.pro/terms

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/
2. Activate the plugin in WordPress
3. Go to TruCookie (left sidebar)
4. Paste API key, then click Connect

Dashboard URL is detected automatically (it shows the current WordPress site URL).

Advanced override of the TruCookie service URL (e.g. self-hosted / staging):
* wp-config.php: define('SC_SERVICE_URL', 'https://your-dashboard-domain.com');
* Or filter: sc_default_service_url

== Development ==

Basic checks:
* php -l trucookie-cmp.php
* php tests/integration-flow.php

== Screenshots ==

1. TruCookie plugin panel in WordPress (connect, banner preview/config, audit, plans).

== Translations ==

The plugin includes translation files (`.po` + `.mo`) for popular WordPress locales:
* English (United States) (`en_US`)
* Polish (`pl_PL`)
* German (`de_DE`)
* Spanish (`es_ES`)
* French (`fr_FR`)
* Italian (`it_IT`)
* Portuguese, Brazil (`pt_BR`)

WordPress will automatically load the matching locale when available.

== Frequently Asked Questions ==

= Does this guarantee that tags won't run before consent? =
No. This is best-effort. Theme/plugins can still inject tags earlier. Use the dashboard audit to confirm technical behavior.

= Does it support Google Consent Mode v2? =
Yes. The plugin supports Google Consent Mode v2 integration and consent state handling.

= Is this a GDPR cookie consent plugin for WordPress? =
It is designed for GDPR-oriented cookie consent workflows, including banner setup and policy/audit checks.

= Can I use it without an account? =
You can configure locally, but connected mode is required for full dashboard sync and audit features.

= Does this work with most themes? =
Yes in most setups. Some themes/plugins can still inject scripts before consent, so verification is always recommended.

== Changelog ==

= 0.1.0 =
* Initial release
