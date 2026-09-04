<?php
/**
 * Plugin Name:       External Image Importer
 * Plugin URI:        https://github.com/quietcactus/wp-auto-upload
 * Description:       Finds externally hosted images in your post content, imports them into the media library and rewrites the URLs to point at your own site.
 * Version:           4.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            quietcactus
 * Author URI:        https://github.com/quietcactus
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       external-image-importer
 * Domain Path:       /languages
 *
 * @package ExternalImageImporter
 *
 * This plugin is a fork of "Auto Upload Images" by Ali Irani, distributed under
 * the same GPL-2.0-or-later licence. See CREDITS.md for details.
 */

declare(strict_types=1);

namespace ExternalImageImporter;

defined('ABSPATH') || exit;

const VERSION = '4.0.0';

define('ExternalImageImporter\PLUGIN_FILE', __FILE__);
define('ExternalImageImporter\PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ExternalImageImporter\PLUGIN_URL', plugin_dir_url(__FILE__));
define('ExternalImageImporter\PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Minimum PHP version this plugin supports.
 */
const MIN_PHP_VERSION = '8.1';

/**
 * Bail out with an admin notice when the host PHP version is too old.
 *
 * Returning early (instead of loading the plugin) keeps sites on unsupported
 * PHP from fataling on modern syntax.
 */
if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(
                sprintf(
                    /* translators: 1: required PHP version, 2: current PHP version */
                    __('External Image Importer requires PHP %1$s or newer. This site runs PHP %2$s, so the plugin has been disabled.', 'external-image-importer'),
                    MIN_PHP_VERSION,
                    PHP_VERSION
                )
            )
        );
    });

    return;
}

require_once __DIR__ . '/src/Autoloader.php';

Autoloader::register();

register_activation_hook(__FILE__, [Settings::class, 'onActivate']);

add_action('plugins_loaded', static function (): void {
    Plugin::instance()->boot();
});
