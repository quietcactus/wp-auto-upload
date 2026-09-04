<?php
/**
 * Plugin bootstrap: hook registration and the content rewriting pass.
 *
 * @package ExternalImageImporter
 */

declare(strict_types=1);

namespace ExternalImageImporter;

use ExternalImageImporter\Admin\SettingsPage;
use WP_Error;

defined('ABSPATH') || exit;

final class Plugin
{
    private static ?self $instance = null;

    /**
     * Guards against re-entering the filter while creating attachments.
     */
    private bool $processing = false;

    private ContentParser $parser;

    private function __construct()
    {
        $this->parser = new ContentParser();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Register WordPress hooks.
     */
    public function boot(): void
    {
        Settings::migrateLegacyOptions();

        add_action('init', [$this, 'loadTextdomain']);
        add_filter('wp_insert_post_data', [$this, 'filterPostData'], 10, 2);

        if (is_admin()) {
            (new SettingsPage())->register();
        }
    }

    /**
     * Load translations. Hooked on `init` because translations are not
     * available any earlier.
     */
    public function loadTextdomain(): void
    {
        load_plugin_textdomain('external-image-importer', false, dirname(PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Import external images referenced by the post being saved.
     *
     * @param array<string, mixed> $data    Slashed, sanitised post data.
     * @param array<string, mixed> $postarr Slashed post data as submitted.
     *
     * @return array<string, mixed>
     */
    public function filterPostData(array $data, array $postarr): array
    {
        if (!$this->shouldProcess($data, $postarr)) {
            return $data;
        }

        $content = (string) wp_unslash((string) $data['post_content']);
        $updated = $this->importImages($content, $postarr);

        if ($updated !== null && $updated !== $content) {
            $data['post_content'] = wp_slash($updated);
        }

        return $data;
    }

    /**
     * Decide whether this save should be inspected at all.
     *
     * @param array<string, mixed> $data    Post data.
     * @param array<string, mixed> $postarr Submitted post data.
     */
    private function shouldProcess(array $data, array $postarr): bool
    {
        if ($this->processing) {
            return false;
        }

        if (empty($data['post_content']) || !is_string($data['post_content'])) {
            return false;
        }

        $postType = (string) ($data['post_type'] ?? ($postarr['post_type'] ?? ''));

        // Attachments are created by this plugin itself; revisions duplicate
        // work that is already done on the parent.
        if (in_array($postType, ['attachment', 'revision'], true)) {
            return false;
        }

        $status = (string) ($data['post_status'] ?? '');

        if (in_array($status, ['auto-draft', 'trash', 'inherit'], true)) {
            return false;
        }

        $postId = (int) ($postarr['ID'] ?? 0);

        if ($postId > 0 && (wp_is_post_revision($postId) || wp_is_post_autosave($postId))) {
            return false;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return false;
        }

        $excluded = Settings::get('exclude_post_types', []);

        if (is_array($excluded) && in_array($postType, $excluded, true)) {
            return false;
        }

        /**
         * Filters whether a save should be scanned for external images.
         *
         * @param bool                 $process Whether to process this save.
         * @param array<string, mixed> $data    Post data.
         * @param array<string, mixed> $postarr Submitted post data.
         */
        return (bool) apply_filters('external_image_importer_should_process', true, $data, $postarr);
    }

    /**
     * Rewrite every external image in `$content`.
     *
     * @param array<string, mixed> $postarr Post data being saved.
     *
     * @return string|null Updated content, or null when nothing changed.
     */
    public function importImages(string $content, array $postarr): ?string
    {
        $tags = $this->parser->parse($content);

        if ($tags === []) {
            return null;
        }

        /**
         * Filters how many images may be imported during a single save.
         *
         * @param int $limit Maximum number of images. 0 removes the limit.
         */
        $limit = (int) apply_filters('external_image_importer_max_images_per_post', 100);

        $this->processing = true;
        $imported         = [];
        $count            = 0;
        $changed          = false;

        try {
            foreach ($tags as $tag) {
                $tagChanged = false;

                foreach ($tag->urls as $url) {
                    if ($limit > 0 && $count >= $limit) {
                        break 2;
                    }

                    if (!array_key_exists($url, $imported)) {
                        $result = (new ImageImporter($url, $tag->alt, $postarr))->import();

                        if (is_wp_error($result)) {
                            $this->logFailure($url, $result);
                            $imported[$url] = null;
                        } else {
                            $imported[$url] = $result;
                            ++$count;
                        }
                    }

                    if ($imported[$url] === null) {
                        continue;
                    }

                    $tag->replaceUrl($url, $imported[$url]['url']);
                    $tagChanged = true;

                    /**
                     * Fires after an external image has been imported.
                     *
                     * @param array<string, mixed> $image   Imported image data.
                     * @param string               $url     Original URL.
                     * @param array<string, mixed> $postarr Post being saved.
                     */
                    do_action('external_image_importer_imported', $imported[$url], $url, $postarr);
                }

                if (!$tagChanged) {
                    continue;
                }

                $alt = '';

                // Take the alt text from the first URL in the tag that
                // actually imported, not simply the first one listed.
                foreach ($tag->urls as $url) {
                    if (isset($imported[$url]['alt']) && $imported[$url]['alt'] !== '') {
                        $alt = (string) $imported[$url]['alt'];
                        break;
                    }
                }

                if ($alt !== '') {
                    $tag->setAlt($alt);
                }

                if ($tag->isModified()) {
                    $content = str_replace($tag->html, $tag->rewritten(), $content);
                    $changed = true;
                }
            }
        } finally {
            $this->processing = false;
        }

        return $changed ? $content : null;
    }

    /**
     * Record why an image could not be imported.
     */
    private function logFailure(string $url, WP_Error $error): void
    {
        /**
         * Fires when an image could not be imported.
         *
         * @param string   $url   URL that failed.
         * @param WP_Error $error Reason for the failure.
         */
        do_action('external_image_importer_import_failed', $url, $error);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                sprintf('External Image Importer: skipped %s (%s)', $url, $error->get_error_message())
            );
        }
    }
}
