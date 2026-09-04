=== External Image Importer ===
Contributors: quietcactus
Tags: images, media, import, external images, migration
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 4.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Imports externally hosted images into your media library when you save a post, and rewrites the image URLs to point at your own site.

== Description ==

Pasting content from another site leaves you hotlinking someone else's images:
if they move, rename or block them, your posts break.

External Image Importer fixes that automatically. Whenever a post is saved, it
looks for `<img>` tags pointing at other domains, downloads those images into
your uploads folder, adds them to the media library, and rewrites the URLs in
the post to your own copy.

= What it does =

* Finds external images in `src`, and in `srcset` and `<source>` where WordPress keeps that markup (see the FAQ)
* Downloads them into the normal WordPress uploads folder and media library
* Rewrites the post content to point at your copy
* Optionally scales images down to a maximum width and height
* Names files and alt attributes from placeholder patterns you control
* Skips post types or domains you exclude
* Can serve imported images from a different base URL, such as a CDN

= Built to be safe =

* Downloads go through the WordPress safe HTTP API, which refuses loopback, link-local and private network addresses, so a pasted URL cannot be used to probe your internal network
* Every download is size-capped, and the type is detected from the file's own bytes rather than from its URL or the server's headers
* Only real raster image types are stored: JPEG, PNG, GIF, WebP, AVIF, BMP and TIFF. SVG is rejected, because it can carry script
* The settings screen is protected by a capability check and a nonce, and every value is validated before it is stored
* The same remote image is only ever downloaded once, however many posts use it

= Placeholders =

Both the file name and the alt text accept these placeholders:

`%filename%`, `%image_alt%`, `%url%`, `%today_date%`, `%year%`, `%month%`,
`%today_day%`, `%post_date%`, `%post_year%`, `%post_month%`, `%post_day%`,
`%random%`, `%timestamp%`, `%postname%`, `%post_id%`

= For developers =

Filters: `external_image_importer_should_process`,
`external_image_importer_max_images_per_post`,
`external_image_importer_max_bytes`,
`external_image_importer_request_timeout`,
`external_image_importer_allow_unsafe_urls`,
`external_image_importer_reuse_attachments`,
`external_image_importer_pattern_tokens`

Actions: `external_image_importer_imported`,
`external_image_importer_import_failed`

Source code and issue tracker: https://github.com/quietcactus/wp-auto-upload

= Credits =

This plugin is a fork of Auto Upload Images by Ali Irani, released under the
same GPL licence. See CREDITS.md in the source repository.

== External services ==

This plugin does not connect to any service of its own. It only downloads the
image URLs that already appear in your own post content, from whatever host
those URLs point at, at the moment you save the post. No data about your site
or your visitors is sent anywhere.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it from the
   Plugins screen in your dashboard.
2. Activate the plugin.
3. Go to **Settings → External Image Importer** to adjust the defaults.

The default settings work without any configuration.

== Frequently Asked Questions ==

= Does it work with the block editor? =

Yes. Images are imported when the post is saved. The editor may keep showing
the original URL until you reload it, but the stored content is already
rewritten.

= What is "Base URL"? =

The address imported images are served from. Leave it as your site URL unless
you serve uploads from a CDN or a separate media domain.

= Why was an image skipped? =

Most often because it is bigger than the download limit, because it is not
actually an image, because its domain is excluded, or because the remote server
returned an error. Enable `WP_DEBUG` and check `wp-content/debug.log`, or hook
`external_image_importer_import_failed`, to see the exact reason.

= Can it import images from a server on my own network? =

Not by default, and that is deliberate: allowing it would let anyone who can
publish a post probe your internal network. If you genuinely need it, return
true from the `external_image_importer_allow_unsafe_urls` filter.

= Does it import images that are already in my media library? =

No. Images already hosted on your own domain, on the configured base URL, or
imported by a previous save are left alone.

= Why was only the src imported and not the srcset images? =

WordPress, not this plugin, is removing them. Core's `wp_kses` allow-list for
post content permits `src` on an `img` but not `srcset`, and does not permit
`<picture>` or `<source>` at all, so that markup is stripped from the content
before any plugin sees it, for every author without the `unfiltered_html`
capability. Administrators on a single site do have that capability, so
srcset candidates written by an administrator are imported normally.

= Why is %post_id% empty in a file name? =

The post ID does not exist yet while a brand new post is being saved for the
first time, so the placeholder resolves to nothing there. It fills in on every
later save of that post. Use `%postname%`, `%timestamp%` or `%random%` if you
need something unique on the first save.

= Will it change existing posts? =

Only when you save them. The plugin does not run a bulk pass over old content.

== Changelog ==

= 4.0.0 =

First release of this fork, rebuilt on top of Auto Upload Images 3.3.2.

Security:

* Downloads now use the WordPress safe HTTP API, closing a server-side request forgery hole that allowed images to be fetched from loopback, link-local and private network addresses.
* Added a configurable maximum download size, so a hostile or oversized remote file can no longer exhaust memory or disk.
* The file type is now detected from the downloaded bytes. Files whose type cannot be established, and types outside a strict image allow-list (SVG included), are rejected instead of being written to the uploads folder without an extension.
* The "reset settings" action was reachable without a nonce, allowing settings to be wiped by cross-site request forgery. Both form actions now check a nonce and the `manage_options` capability.
* Filename and alt patterns are no longer interpreted as regular expressions, and imported URLs are no longer used as regular-expression replacements.
* Saving the settings form no longer discards options that were not part of the submitted form.

Fixes:

* Unchecking every "exclude post type" box now clears the list instead of keeping the old value, and duplicate entries no longer accumulate.
* `0` and empty values can now be saved for the size fields.
* Resizing no longer creates a second, duplicate attachment and no longer leaves the full-size file orphaned.
* Alt attributes are rewritten only on the image they belong to, instead of everywhere in the post.
* Fixed PHP 8 warnings and deprecations when a post has no ID, no name or no date.
* Fixed URL detection for `srcset`, `<source>` elements, single-quoted and unquoted attributes, and URLs containing HTML entities.
* Attachments are no longer created for the same remote image twice.
* Images are stored in the uploads folder matching the post's own date.

Housekeeping:

* Requires PHP 8.1 and WordPress 6.0.
* Rewritten as namespaced, autoloaded classes with a unique prefix.
* Added an uninstall routine, a unit test suite, a WordPress integration test suite, PHP_CodeSniffer configuration and GitHub Actions CI.

== Upgrade Notice ==

= 4.0.0 =
Security release and rewrite. Requires PHP 8.1. Settings are carried over from Auto Upload Images automatically.
