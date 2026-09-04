# External Image Importer

A WordPress plugin that imports externally hosted images into your media
library when you save a post, and rewrites the post content to point at your
own copy.

**Version:** 4.0.0 · **Requires:** WordPress 6.0+, PHP 8.1+ · **Licence:** GPL-2.0-or-later

This is a maintained fork of [Auto Upload Images](https://github.com/airani/wp-auto-upload)
by Ali Irani, rebuilt for modern PHP with a number of security fixes. See
[CREDITS.md](CREDITS.md).

---

## What it does

When a post is saved, the plugin scans the content for `<img>` and `<source>`
tags pointing at other domains, downloads those images into the uploads folder,
registers them in the media library, and rewrites the URLs in the post.

* Reads `src` and `srcset`, including `<source>` inside `<picture>`
* Optional maximum width/height, applied on import
* Filenames and alt text built from placeholder patterns
* Per-domain and per-post-type exclusions
* Optional CDN base URL for the rewritten links
* Each remote image is downloaded once, however many posts reference it

## Security posture

The 4.0.0 rewrite closed several holes that were present upstream:

| Issue | Fix |
| --- | --- |
| Server-side request forgery: any URL in post content was fetched, including `127.0.0.1` and cloud metadata endpoints | Requests go through `wp_safe_remote_get()`; opt out per-site with the `external_image_importer_allow_unsafe_urls` filter |
| Unbounded download size | Configurable cap (default 25 MB), enforced with `limit_response_size` |
| A file whose mime type was unknown was written to uploads with no extension | Type is sniffed from the bytes and matched against a strict allow-list; SVG is never accepted |
| "Reset settings" ran without a nonce (CSRF) | Both form actions verify a nonce and `manage_options` |
| User-supplied patterns were compiled as regular expressions, and URLs were used as regex replacements | All substitution is done with `strtr()` / `str_replace()` |
| Saving the form dropped options that were not submitted | The full option set is validated and written every time |

## Installation

Download or build a ZIP (`bin/build-zip.sh`) and install it from
**Plugins → Add New → Upload Plugin**, then visit
**Settings → External Image Importer**.

Settings from Auto Upload Images (`aui-setting`) are migrated automatically the
first time this plugin runs. The original option is left untouched.

## Placeholders

Available in both the file name and the alt text:

`%filename%` `%image_alt%` `%url%` `%today_date%` `%year%` `%month%`
`%today_day%` `%post_date%` `%post_year%` `%post_month%` `%post_day%`
`%random%` `%timestamp%` `%postname%` `%post_id%`

`%date%` and `%day%` are still accepted and rewritten to `%today_date%` and
`%today_day%`.

## Hooks

### Filters

| Filter | Purpose |
| --- | --- |
| `external_image_importer_should_process` | Skip a save entirely |
| `external_image_importer_max_images_per_post` | Cap images imported per save (default 100) |
| `external_image_importer_max_bytes` | Maximum download size in bytes |
| `external_image_importer_request_timeout` | HTTP timeout in seconds (default 15) |
| `external_image_importer_allow_unsafe_urls` | Allow private/loopback hosts (default false) |
| `external_image_importer_reuse_attachments` | Reuse a previously imported attachment (default true) |
| `external_image_importer_pattern_tokens` | Add or override placeholders |

### Actions

| Action | Fired |
| --- | --- |
| `external_image_importer_imported` | After an image is imported |
| `external_image_importer_import_failed` | When an image is skipped, with the `WP_Error` |

## Development

See [LOCAL-DEVELOPMENT.md](LOCAL-DEVELOPMENT.md) for the full setup. In short:

```bash
composer install
composer run lint        # PHP_CodeSniffer
composer run test:unit   # no WordPress required
npm install && npm run env:start   # a local WordPress at :8888
```

## Layout

```
external-image-importer.php   Plugin header, constants, bootstrap
uninstall.php                 Removes options and meta on delete
src/
  Autoloader.php              PSR-4 autoloading for the plugin namespace
  Plugin.php                  Hooks, and the save-post rewriting pass
  Settings.php                Defaults, validation, storage, legacy migration
  ContentParser.php           Finds image tags and their URLs
  ImageTag.php                One tag, and the rewrites applied to it
  ImageImporter.php           Download, validate, store, attach
  PatternResolver.php         %placeholder% expansion
  Url.php                     URL parsing helpers
  Admin/SettingsPage.php      Settings screen and form handling
  views/settings-page.php     Settings screen markup
tests/unit/                   Fast tests, no WordPress needed
tests/integration/            WordPress test-suite tests
```

## Licence

GPL-2.0-or-later. See [LICENSE](LICENSE).
