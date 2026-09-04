<?php
/**
 * Settings screen markup.
 *
 * @package ExternalImageImporter
 *
 * @var array<string, mixed> $options Current settings.
 * @var string               $nonce   Nonce action for the form.
 * @var string               $notice  Admin notice to display, if any.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$eximgimp_tokens = [
    '%filename%',
    '%image_alt%',
    '%url%',
    '%today_date%',
    '%year%',
    '%month%',
    '%today_day%',
    '%post_date%',
    '%post_year%',
    '%post_month%',
    '%post_day%',
    '%random%',
    '%timestamp%',
    '%postname%',
    '%post_id%',
];

$eximgimp_token_list = implode(
    ', ',
    array_map(
        static fn (string $token): string => '<code dir="ltr">' . esc_html($token) . '</code>',
        $eximgimp_tokens
    )
);

$eximgimp_excluded_types = is_array($options['exclude_post_types'] ?? null) ? $options['exclude_post_types'] : [];

// Attachments and revisions are never processed, so there is nothing to exclude.
$eximgimp_post_types = array_filter(
    get_post_types([], 'objects'),
    static fn (object $type): bool => !in_array($type->name, ['attachment', 'revision'], true)
);
$eximgimp_editor_ready   = function_exists('wp_image_editor_supports') ? wp_image_editor_supports() : false;
?>
<div class="wrap">
	<h1><?php esc_html_e('External Image Importer', 'external-image-importer'); ?></h1>

	<?php if ($notice !== '') : ?>
		<div class="notice notice-success is-dismissible">
			<p><strong><?php echo esc_html($notice); ?></strong></p>
		</div>
	<?php endif; ?>

	<div id="poststuff">
		<div id="post-body" class="metabox-holder columns-2">
			<div id="post-body-content" style="position: relative">
				<div class="stuffbox" style="padding: 0 20px">
					<form method="post" action="">
						<?php wp_nonce_field($nonce); ?>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="eximgimp-base-url"><?php esc_html_e('Base URL', 'external-image-importer'); ?></label>
								</th>
								<td>
									<input type="text" id="eximgimp-base-url" name="base_url" dir="ltr" class="regular-text"
										value="<?php echo esc_attr((string) ($options['base_url'] ?? '')); ?>">
									<p class="description">
										<?php
										printf(
											/* translators: %s: list of example URLs */
											esc_html__('Serve imported images from this address instead of your site URL, for example a CDN. Examples: %s', 'external-image-importer'),
											'<code dir="ltr">https://example.com</code>, <code dir="ltr">https://cdn.example.com</code>, <code dir="ltr">/</code>'
										);
										?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="eximgimp-image-name"><?php esc_html_e('Image file name', 'external-image-importer'); ?></label>
								</th>
								<td>
									<input type="text" id="eximgimp-image-name" name="image_name" dir="ltr" class="regular-text"
										value="<?php echo esc_attr((string) ($options['image_name'] ?? '')); ?>">
									<p class="description">
										<?php
										printf(
											/* translators: %s: list of available placeholders */
											esc_html__('File name given to imported images. Available placeholders: %s', 'external-image-importer'),
											wp_kses($eximgimp_token_list, ['code' => ['dir' => []]])
										);
										?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="eximgimp-alt-name"><?php esc_html_e('Alt text', 'external-image-importer'); ?></label>
								</th>
								<td>
									<input type="text" id="eximgimp-alt-name" name="alt_name" dir="ltr" class="regular-text"
										value="<?php echo esc_attr((string) ($options['alt_name'] ?? '')); ?>">
									<p class="description">
										<?php
										printf(
											/* translators: %s: list of available placeholders */
											esc_html__('Alt attribute written back into the post. Available placeholders: %s', 'external-image-importer'),
											wp_kses($eximgimp_token_list, ['code' => ['dir' => []]])
										);
										?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<?php esc_html_e('Maximum image size', 'external-image-importer'); ?>
									<?php if (!$eximgimp_editor_ready) : ?>
										<br><small><?php esc_html_e('(unavailable)', 'external-image-importer'); ?></small>
									<?php endif; ?>
								</th>
								<td>
									<label for="eximgimp-max-width"><?php esc_html_e('Max width', 'external-image-importer'); ?></label>
									<input type="number" id="eximgimp-max-width" name="max_width" class="small-text" min="0" step="1"
										placeholder="0" value="<?php echo esc_attr((string) ($options['max_width'] ?? 0)); ?>"
										<?php disabled(!$eximgimp_editor_ready); ?>>

									<label for="eximgimp-max-height"><?php esc_html_e('Max height', 'external-image-importer'); ?></label>
									<input type="number" id="eximgimp-max-height" name="max_height" class="small-text" min="0" step="1"
										placeholder="0" value="<?php echo esc_attr((string) ($options['max_height'] ?? 0)); ?>"
										<?php disabled(!$eximgimp_editor_ready); ?>>

									<p class="description">
										<?php esc_html_e('Imported images larger than this are scaled down, keeping their aspect ratio. Leave both at 0 to keep the original size.', 'external-image-importer'); ?>
									</p>

									<?php if (!$eximgimp_editor_ready) : ?>
										<p><strong><?php esc_html_e('Resizing needs the GD or Imagick PHP extension, which is not enabled on this server.', 'external-image-importer'); ?></strong></p>
									<?php endif; ?>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="eximgimp-max-file-size"><?php esc_html_e('Maximum download size', 'external-image-importer'); ?></label>
								</th>
								<td>
									<input type="number" id="eximgimp-max-file-size" name="max_file_size" class="small-text" min="1" max="512" step="1"
										value="<?php echo esc_attr((string) ($options['max_file_size'] ?? 25)); ?>">
									<?php esc_html_e('MB', 'external-image-importer'); ?>
									<p class="description">
										<?php esc_html_e('Remote files larger than this are skipped. This protects the server from very large or hostile downloads.', 'external-image-importer'); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e('Exclude post types', 'external-image-importer'); ?></th>
								<td>
									<fieldset>
										<legend class="screen-reader-text">
											<?php esc_html_e('Post types excluded from importing', 'external-image-importer'); ?>
										</legend>
										<?php foreach ($eximgimp_post_types as $eximgimp_post_type) : ?>
											<label style="display:block">
												<input type="checkbox" name="exclude_post_types[]"
													value="<?php echo esc_attr($eximgimp_post_type->name); ?>"
													<?php checked(in_array($eximgimp_post_type->name, $eximgimp_excluded_types, true)); ?>>
												<?php echo esc_html($eximgimp_post_type->labels->singular_name . ' (' . $eximgimp_post_type->name . ')'); ?>
											</label>
										<?php endforeach; ?>
									</fieldset>
									<p class="description">
										<?php esc_html_e('Content of the selected post types is left untouched.', 'external-image-importer'); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="eximgimp-exclude-urls"><?php esc_html_e('Exclude domains', 'external-image-importer'); ?></label>
								</th>
								<td>
									<textarea id="eximgimp-exclude-urls" name="exclude_urls" rows="8" class="large-text code"
										dir="ltr" placeholder="example.com"><?php echo esc_textarea((string) ($options['exclude_urls'] ?? '')); ?></textarea>
									<p class="description">
										<?php esc_html_e('One domain per line. Images hosted on these domains are never imported.', 'external-image-importer'); ?>
									</p>
								</td>
							</tr>
						</table>

						<p class="submit">
							<?php submit_button(null, 'primary', 'eximgimp_submit', false); ?>
							<?php
							submit_button(
								__('Reset to defaults', 'external-image-importer'),
								'secondary small',
								'eximgimp_reset',
								false,
								[
									'onclick' => 'return confirm("' . esc_js(__('Reset all settings to their defaults?', 'external-image-importer')) . '");',
								]
							);
							?>
						</p>
					</form>
				</div>
			</div>

			<div id="postbox-container-1" class="postbox-container">
				<div class="postbox">
					<h2 class="hndle"><?php esc_html_e('About this plugin', 'external-image-importer'); ?></h2>
					<div class="inside">
						<p>
							<?php esc_html_e('When a post is saved, images hosted on other sites are downloaded into your media library and the URLs in the post are rewritten to point at your own copy.', 'external-image-importer'); ?>
						</p>
						<ul>
							<li>
								<a href="https://github.com/quietcactus/wp-auto-upload" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e('Source code on GitHub', 'external-image-importer'); ?>
								</a>
							</li>
							<li>
								<a href="https://github.com/quietcactus/wp-auto-upload/issues/new" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e('Report a bug', 'external-image-importer'); ?>
								</a>
							</li>
						</ul>
						<hr>
						<p class="description">
							<?php
							printf(
								/* translators: %s: name of the original plugin author */
								esc_html__('Forked from the Auto Upload Images plugin by %s and released under the same GPL licence.', 'external-image-importer'),
								esc_html('Ali Irani')
							);
							?>
						</p>
					</div>
				</div>
			</div>
		</div>
		<br class="clear">
	</div>
</div>
