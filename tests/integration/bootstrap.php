<?php
/**
 * Bootstrap for the WordPress integration suite.
 *
 * Run `bin/install-wp-tests.sh` (or `composer wp:install-tests`) once before
 * using this suite.
 *
 * @package ExternalImageImporter
 */

declare(strict_types=1);

$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
	$_tests_dir = getenv('WP_DEVELOP_DIR') ? getenv('WP_DEVELOP_DIR') . '/tests/phpunit' : null;
}

if (!$_tests_dir) {
	$_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
	fwrite(
		STDERR,
		"Could not find {$_tests_dir}/includes/functions.php." . PHP_EOL
		. 'Run bin/install-wp-tests.sh first, or start the environment with `npm run env:start`.' . PHP_EOL
	);

	exit(1);
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname(__DIR__, 2) . '/external-image-importer.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
