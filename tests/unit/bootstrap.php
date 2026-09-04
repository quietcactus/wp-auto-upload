<?php
/**
 * Bootstrap for the framework-independent unit suite.
 *
 * @package ExternalImageImporter
 */

declare(strict_types=1);

require_once __DIR__ . '/stubs.php';
require_once dirname(__DIR__, 2) . '/src/Autoloader.php';

ExternalImageImporter\Autoloader::register();

if (!defined('ExternalImageImporter\VERSION')) {
    define('ExternalImageImporter\VERSION', '4.0.0');
}
