# Running this plugin locally

Everything below runs on your own machine. Nothing here needs a hosted
WordPress install.

## 1. Get the code

```bash
git clone https://github.com/quietcactus/wp-auto-upload.git external-image-importer
cd external-image-importer
git checkout claude/wordpress-plugin-modernize-gjbnoo
```

Requirements: PHP 8.1+, [Composer](https://getcomposer.org/), and Docker
(only if you want a throwaway WordPress to click around in).

```bash
composer install
```

## 2. Start a WordPress to test against

Pick whichever you prefer. Both mount this folder as the plugin, so edits are
live.

### Option A: `wp-env` (needs Node 18+ and Docker)

```bash
npm install
npm run env:start
```

* Site: <http://localhost:8888> (admin / password)
* Tests site: <http://localhost:8889>

Activate the plugin, then open **Settings → External Image Importer**.

Stop it with `npm run env:stop`, wipe it with `npm run env:destroy`.

### Option B: plain Docker Compose (no Node)

```bash
docker compose up -d
```

Then finish the WordPress install at <http://localhost:8888> and activate
**External Image Importer** under Plugins.

WP-CLI is available through the optional `cli` profile:

```bash
docker compose run --rm cli wp plugin activate external-image-importer
```

Tear it down with `docker compose down` (add `-v` to delete the database).

### Option C: an existing local stack

Symlink or copy the folder into `wp-content/plugins/` of any Local, MAMP,
Valet or Docker install you already run:

```bash
ln -s "$(pwd)" /path/to/wordpress/wp-content/plugins/external-image-importer
```

## 3. Try it out

1. Create a post and paste in an `<img>` tag pointing at an image on another
   site, for example `<img src="https://picsum.photos/600/400.jpg" alt="Test">`.
2. Save the post.
3. Reload the editor: the `src` now points at your own `wp-content/uploads`
   directory, and the image is in **Media**.

Failures are silent by design (a broken remote image should never block a
save). Turn on `WP_DEBUG` and watch `wp-content/debug.log` to see why an image
was skipped, or hook `external_image_importer_import_failed`.

## 4. Tests and linting

```bash
composer run lint          # PHP_CodeSniffer: WordPress security + i18n sniffs
composer run lint:fix      # auto-fix what can be auto-fixed
composer run test:unit     # fast, no WordPress needed
```

The integration suite needs the WordPress test library and a MySQL/MariaDB
database. The library is checked out with Subversion, so install that first if
you do not have it (`brew install svn`, `apt install subversion`):

```bash
bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest
composer run test:integration
```

If you started `wp-env` above, it already ships a database, so this works too:

```bash
npm run test:php
```

## 5. Building a release ZIP

```bash
bin/build-zip.sh
```

This writes `build/external-image-importer.zip` containing only the runtime
files listed outside `.distignore`. That ZIP is what you upload to
WordPress.org (or install by hand on a site).
