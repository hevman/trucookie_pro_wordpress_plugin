=== TruCookie CMP (Consent Mode v2) ===
Contributors: trucookie
Tags: consent mode, cmp, cookie banner, audit
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress to the TruCookie dashboard. Inject the CMP snippet, add a verification meta tag, and run audits.

== Description ==

This plugin connects your WordPress site to the TruCookie dashboard using an API key generated in your dashboard profile.

Guest mode (no API key):
* Provides links to run a free audit and create an account.

Connected mode:
* Syncs your site with the dashboard
* (Optional) injects the CMP snippet into <head>
* (Optional) injects verification meta tag into <head>
* Triggers best-effort verification in the dashboard

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/
2. Activate the plugin in WordPress
3. Go to TruCookie (left sidebar)
4. Paste API key, then click Connect

Dashboard URL is detected automatically (it shows the current WordPress site URL).

Advanced override of the TruCookie service URL (e.g. self-hosted / staging):
* wp-config.php: define('SC_SERVICE_URL', 'https://your-dashboard-domain.com');
* Or filter: sc_default_service_url

== Frequently Asked Questions ==

= Does this guarantee that tags won’t run before consent? =
No. This is best-effort. Theme/plugins can still inject tags earlier. Use the dashboard audit to confirm technical behavior.

== Changelog ==

= 0.1.0 =
* Initial release
