# TruCookie CMP Stable

TruCookie CMP Stable is a WordPress consent plugin built around practical cookie banner management, Google Consent Mode v2 support, and a local-first privacy workflow.

It works in local mode by default and can optionally connect to TruCookie services when a project needs external sync or connected features.

## Key features

- Cookie banner for WordPress
- Google Consent Mode v2 support
- Basic and Advanced consent flows
- wp-consent-api bridge support
- Local consent storage
- Optional local consent logging with CSV export
- Optional script blocking by consent category
- Optional connected mode with TruCookie services
- English, Polish, and German labels

## Who it is for

TruCookie CMP Stable is built for:

- WordPress sites that need a practical consent banner
- teams implementing Google Consent Mode v2
- agencies handling privacy and consent setup for clients
- site owners who want a plugin that can work without mandatory external API calls

## Installation

1. Copy the plugin to `wp-content/plugins/trucookie-cmp-consent-mode-v2` or install it as a ZIP in WordPress admin.
2. Activate the plugin.
3. Open `TruCookie CMP` in wp-admin.
4. Configure banner settings and test consent behavior.

For testing, you can reset consent with:

```text
?tcs_reset_consent=1&tcs_force_banner=1
```

## Repository structure

- [trucookie-cmp-consent-mode-v2.php](/mnt/data_a/www_work/saas_cookie/wordpress-plugin/trucookie-cmp-consent-mode-v2/trucookie-cmp-consent-mode-v2.php): plugin bootstrap
- [assets/](/mnt/data_a/www_work/saas_cookie/wordpress-plugin/trucookie-cmp-consent-mode-v2/assets): plugin assets
- [includes/](/mnt/data_a/www_work/saas_cookie/wordpress-plugin/trucookie-cmp-consent-mode-v2/includes): plugin logic and services
- [languages/](/mnt/data_a/www_work/saas_cookie/wordpress-plugin/trucookie-cmp-consent-mode-v2/languages): translations

## Documentation

Main plugin metadata and user-facing documentation live in:

- [readme.txt](/mnt/data_a/www_work/saas_cookie/wordpress-plugin/trucookie-cmp-consent-mode-v2/readme.txt)

## Notes

- Local mode works without external API calls by default.
- Connected mode is optional.
- Consent Mode support in the plugin is technical tooling, not legal advice.

## License

GPLv2 or later. See plugin headers and [readme.txt](/mnt/data_a/www_work/saas_cookie/wordpress-plugin/trucookie-cmp-consent-mode-v2/readme.txt) for packaging metadata.
