=== TruCookie CMP Stable ===
Contributors: trucookie
Tags: cookie banner, consent, gdpr, privacy, google consent mode
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.4.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie banner for WordPress with local runtime, wp-consent-api bridge, Google Consent Mode v2, and optional TruCookie connected mode.

== Description ==

TruCookie CMP Stable provides:

* Cookie banner with English and Polish labels
* GDPR / US mode switch
* Google Consent Mode v2 defaults + updates
* Optional script blocker by consent category
* Optional server-side consent forwarding to TruCookie
* Local consent logs with CSV export
* wp-consent-api bridge (`wp_set_consent`)

The plugin works fully in local mode. Connected mode is optional.

== Installation ==

1. Upload plugin folder to `/wp-content/plugins/` or install ZIP in wp-admin.
2. Activate **TruCookie CMP Stable**.
3. Go to **TruCookie CMP** in wp-admin.
4. Configure banner and save settings.
5. Test in incognito mode with `?tcs_reset_consent=1&tcs_force_banner=1`.

== Frequently Asked Questions ==

= Why is there no banner after install? =

Most often consent is already stored in localStorage/cookie. Reset with:
`?tcs_reset_consent=1&tcs_force_banner=1`.

= Is TruCookie API required? =

No. Local mode does not require external API.

= Does this plugin send data externally by default? =

No. External forwarding is disabled by default and must be enabled in settings.

== Privacy ==

By default the plugin stores consent decision locally in visitor browser.

Optional local logging in WordPress database stores:

* consent state
* page URL and referrer
* timestamp
* plugin version and source

Optional metadata collection (disabled by default) can add:

* user agent
* IP hint

Optional forwarding to TruCookie (disabled by default) sends consent event to configured endpoint.

Site owner is responsible for legal basis, privacy notices, and consent language.

== External Services ==

This plugin can connect to external TruCookie service only when enabled by admin.

Service: TruCookie API  
Purpose: Optional consent log forwarding / connected renderer  
Data sent: consent payload, URL/referrer, timestamp, plugin metadata, and optionally user agent/IP hint  
Endpoint: configured by admin (`Service URL` + `Consent log path`)  
Provider: TruCookie  
Terms: https://trucookie.pro/terms  
Privacy Policy: https://trucookie.pro/privacy

== Screenshots ==

1. TruCookie CMP settings screen in WordPress admin.

== Changelog ==

= 0.4.4 =

* Hardened public consent endpoint (same-site check + rate limiting)
* Added compliance toggles:
  * collect technical metadata (off by default)
  * forward consent logs externally (off by default)
* Added textdomain loading for i18n bootstrap
* Added uninstall cleanup
* Added release gate script
* New architecture

= 0.4.3 =

* Banner/admin CSS moved to external files
* Improved policy URL resolution
* Updated banner copy
