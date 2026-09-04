<?php
/**
 * Removes everything this plugin stored, when it is deleted from the admin.
 *
 * The legacy "aui-setting" option is deliberately left alone: it belongs to
 * the upstream Auto Upload Images plugin, which may still be installed.
 *
 * @package ExternalImageImporter
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

const EXIMGIMP_OPTION_KEY   = 'eximgimp_settings';
const EXIMGIMP_SOURCE_META  = '_eximgimp_source_url';

/**
 * Delete the plugin's data for the current site.
 */
function eximgimp_uninstall_site(): void {
	delete_option(EXIMGIMP_OPTION_KEY);
	delete_post_meta_by_key(EXIMGIMP_SOURCE_META);
}

if (is_multisite()) {
	$eximgimp_site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	);

	foreach ($eximgimp_site_ids as $eximgimp_site_id) {
		switch_to_blog((int) $eximgimp_site_id);
		eximgimp_uninstall_site();
		restore_current_blog();
	}

	delete_site_option(EXIMGIMP_OPTION_KEY);
} else {
	eximgimp_uninstall_site();
}
