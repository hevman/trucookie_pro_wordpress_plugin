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

== Google Consent Mode Documentation ==

Official Google references used by this plugin documentation:

* Consent Mode overview and implementation guide: https://developers.google.com/tag-platform/security/guides/consent
* Google business data responsibility: https://business.safety.google/privacy/
* Google tag consent APIs: https://developers.google.com/tag-platform/gtagjs/reference#consent
* Tag Manager consent APIs: https://developers.google.com/tag-platform/tag-manager/templates/consent-apis

TruCookie CMP Stable follows Consent Mode v2 mapping for:

* `ad_storage`
* `analytics_storage`
* `ad_user_data`
* `ad_personalization`

Notes:

* This documentation explains technical signal handling.
* It does not claim that Consent Mode alone satisfies any specific legal or regulatory requirement.

== Consent Mode Configuration (Basic and Advanced) ==

In TruCookie CMP -> Compliance & Integrations -> Google Consent Mode v2:

* Mode: Advanced
  * Set consent defaults before measurement.
  * Google tags can load before user consent and are updated after user choice.
* Mode: Basic
  * Block Google tags before user consent.
  * Enable tags and update consent only after user interaction.

Default implementation order (as recommended by Google):

1. Load Google tag bootstrap (`dataLayer` + `gtag` function).
2. Call `gtag('consent','default',...)` before `config`/`event`.
3. If CMP/banner can load asynchronously, use `wait_for_update`.
4. Call `gtag('consent','update',...)` immediately on consent interaction, on the same page.
5. Persist user choice and replay update on subsequent pages.

Core settings exposed in plugin GUI:

* `Enable GCM` enables default and update commands for required consent types.
* `wait_for_update (ms)` controls the async waiting window.
* `Mode` selects Basic vs Advanced behavior.
* `Google developer ID` sets developer identification where configured.
* `Geo-target banner` controls where banner is shown (for region-specific behavior).

Advanced options such as `url_passthrough` and `ads_data_redaction` are not enabled automatically by this plugin and should be configured explicitly in the Google tag setup when needed.

== Banner Requirements Guidance ==

The plugin provides a full graphical user interface (GUI) for banner setup (no custom HTML/JavaScript required for standard setup).

Default template guidance for Consent Mode without TCF:

* Banner text explains data collection for personalization and analytics.
* Banner includes an affirmative consent option (`Accept All`).
* Banner and preferences include links to:
  * Cookie Policy
  * Privacy Policy
  * Google business data responsibility (`https://business.safety.google/privacy/`)

The admin UI recommends using this default template when enabling Consent Mode without TCF.

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
