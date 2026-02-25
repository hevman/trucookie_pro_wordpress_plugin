# WordPress.org Deploy Notes

This plugin ZIP is for review/testing.  
WordPress.org listing images are served from the plugin SVN repository root `/assets` directory, not from plugin `/trunk`.

## Required listing assets

Place these files in SVN path: `/assets`

- `banner-1544x500.png`
- `banner-772x250.png`
- `icon-256x256.png`
- `icon-128x128.png`
- `screenshot-1.png`

Prepared copies are kept in this repo at:

- `wporg/assets/`

## Minimal SVN structure

- `/assets` (listing icons, banners, screenshots)
- `/trunk` (plugin code + `readme.txt`)
- `/tags/0.4.4` (release snapshot)

## Typical release flow

1. Copy plugin code to `/trunk`.
2. Copy `wporg/assets/*` to SVN `/assets`.
3. Copy `/trunk` to `/tags/<version>`.
4. Commit all changes in one SVN commit.

