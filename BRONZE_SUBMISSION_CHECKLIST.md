# TruCookie WP Plugin - Bronze Submission Checklist

Use this checklist before sending plugin/review updates tied to CMP Bronze scope.

## Product Positioning

- [ ] Plugin copy states advertiser Consent Mode v2 scope.
- [ ] No claim of publisher-certified CMP / full IAB TCF support.
- [ ] FAQ/readme wording is consistent with website scope statement.

## Readme Disclosure

- [ ] `Third-party services` section is complete.
- [ ] Legal links are valid:
  - [ ] `https://trucookie.pro/cookies`
  - [ ] `https://trucookie.pro/privacy`
  - [ ] `https://trucookie.pro/terms`
- [ ] `Scope and non-scope` section is present.

## Functional Smoke (WP Admin)

- [ ] connect with API key works
- [ ] sync/verify action works
- [ ] audit action works
- [ ] plans links do not return 404
- [ ] banner preview shows legal links

## Packaging

- [ ] main plugin file and slug are correct
- [ ] no accidental nested directory (`trucookie/trucookie/...`)
- [ ] release zip excludes local temp artifacts

## Final Evidence

- [ ] screenshot: plugin dashboard
- [ ] screenshot: readme disclosure + scope
- [ ] output: legal URL checks (`curl -I`)

