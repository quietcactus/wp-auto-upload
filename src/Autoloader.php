<?php
/**
 * PSR-4 style autoloader for the plugin namespace.
 *
 * @package ExternalImageImporter
 */

declare(strict_types=1);

namespace ExternalImageImporter;

defined('ABSPATH') || exit;

final class Autoloader
{
    private const NAMESPACE_PREFIX = __NAMESPACE__ . '\\';

    /**
     * Register the autoloader with SPL.
     */
    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    /**
     * Map a fully qualified class name to a file inside src/.
     */
    public static function load(string $class): void
    {
        if (!str_starts_with($class, self::NAMESPACE_PREFIX)) {
            return;
        }

        $relative = substr($class, strlen(self::NAMESPACE_PREFIX));
        $path     = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

        // Guard against directory traversal via a crafted class name.
        $real = realpath($path);
        $base = realpath(__DIR__);

        if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            return;
        }

        require_once $real;
    }
}
