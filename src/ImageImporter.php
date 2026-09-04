<?php
/**
 * Downloads a single remote image into the media library.
 *
 * @package ExternalImageImporter
 */

declare(strict_types=1);

namespace ExternalImageImporter;

use WP_Error;

defined('ABSPATH') || exit;

final class ImageImporter
{
    /**
     * Post meta key recording where an imported attachment came from.
     */
    public const SOURCE_META_KEY = '_eximgimp_source_url';

    /**
     * Image mime types the plugin will store, and the extension used for each.
     *
     * Anything outside this list (SVG in particular, which can carry script)
     * is rejected before it ever reaches the uploads directory.
     *
     * @var array<string, string>
     */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/bmp'  => 'bmp',
        'image/tiff' => 'tiff',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    /**
     * File extensions accepted when reusing the remote filename.
     *
     * @var array<int, string>
     */
    private const FILENAME_EXTENSIONS = ['jpg', 'jpeg', 'jpe', 'png', 'gif', 'bmp', 'tif', 'tiff', 'webp', 'avif'];

    /**
     * @param string               $url  Image URL exactly as written in the content.
     * @param string|null          $alt  Alt text of the tag the URL came from.
     * @param array<string, mixed> $post Post data currently being saved.
     */
    public function __construct(
        private readonly string $url,
        private readonly ?string $alt,
        private readonly array $post
    ) {
    }

    /**
     * Import the image.
     *
     * @return array{attachment_id:int,url:string,path:string,alt:string,mime_type:string,filename:string}|WP_Error
     */
    public function import(): array|WP_Error
    {
        $url = Url::normalize($this->url);

        if (!Url::isFetchable($url)) {
            return new WP_Error('eximgimp_invalid_url', __('The URL is not an absolute http(s) URL.', 'external-image-importer'));
        }

        if (!$this->isExternal($url)) {
            return new WP_Error('eximgimp_not_external', __('The image is already hosted on this site.', 'external-image-importer'));
        }

        if ($this->isExcluded($url)) {
            return new WP_Error('eximgimp_excluded_host', __('The host of this image is excluded in the plugin settings.', 'external-image-importer'));
        }

        $existing = $this->findPreviouslyImported($url);

        if ($existing !== null) {
            return $existing;
        }

        $body = $this->fetch($url);

        if (is_wp_error($body)) {
            return $body;
        }

        $mime = self::detectMimeType($body);

        if ($mime === null || !isset(self::ALLOWED_MIME_TYPES[$mime])) {
            return new WP_Error(
                'eximgimp_unsupported_type',
                __('The downloaded file is not a supported image type.', 'external-image-importer')
            );
        }

        $filename = $this->buildFilename($url, self::ALLOWED_MIME_TYPES[$mime]);
        $upload   = wp_upload_bits($filename, null, $body, $this->uploadTime());

        if (!is_array($upload) || !empty($upload['error']) || empty($upload['file'])) {
            return new WP_Error(
                'eximgimp_upload_failed',
                is_array($upload) && !empty($upload['error'])
                    ? (string) $upload['error']
                    : __('The image could not be written to the uploads directory.', 'external-image-importer')
            );
        }

        unset($body);

        $this->maybeResize((string) $upload['file']);

        $alt           = $this->resolvedAlt($url);
        $attachmentId  = $this->attach((string) $upload['file'], (string) $upload['url'], $mime, $filename, $alt);

        if (is_wp_error($attachmentId)) {
            wp_delete_file((string) $upload['file']);

            return $attachmentId;
        }

        update_post_meta($attachmentId, self::SOURCE_META_KEY, $url);

        return [
            'attachment_id' => $attachmentId,
            'url'           => $this->applyBaseUrl((string) $upload['url']),
            'path'          => (string) $upload['file'],
            'alt'           => $alt,
            'mime_type'     => $mime,
            'filename'      => $filename,
        ];
    }

    /**
     * True when the URL points at a host other than this site.
     */
    private function isExternal(string $url): bool
    {
        $host = Url::host($url);

        if ($host === null) {
            return false;
        }

        $localHosts = array_filter([
            Url::host(home_url()),
            Url::host(site_url()),
            Url::host((string) Settings::get('base_url')),
        ]);

        return !in_array($host, $localHosts, true);
    }

    /**
     * True when the URL's host appears in the "Exclude Domains" setting.
     */
    private function isExcluded(string $url): bool
    {
        $excluded = (string) Settings::get('exclude_urls', '');

        if (trim($excluded) === '') {
            return false;
        }

        $host = Url::host($url);

        if ($host === null) {
            return true;
        }

        foreach (preg_split('/[\r\n]+/', $excluded) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '' && Url::host($line) === $host) {
                return true;
            }
        }

        return false;
    }

    /**
     * Look for an attachment imported from this exact URL in an earlier run.
     *
     * @return array{attachment_id:int,url:string,path:string,alt:string,mime_type:string,filename:string}|null
     */
    private function findPreviouslyImported(string $url): ?array
    {
        /**
         * Filters whether previously imported images are reused instead of
         * downloaded again.
         *
         * @param bool   $reuse Whether to reuse. Default true.
         * @param string $url   Source URL.
         */
        if (!apply_filters('external_image_importer_reuse_attachments', true, $url)) {
            return null;
        }

        $ids = get_posts([
            'post_type'              => 'attachment',
            'post_status'            => 'inherit',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'meta_key'               => self::SOURCE_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value'             => $url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ]);

        if ($ids === []) {
            return null;
        }

        $attachmentId  = (int) $ids[0];
        $attachmentUrl = wp_get_attachment_url($attachmentId);
        $path          = get_attached_file($attachmentId);

        if ($attachmentUrl === false || $path === false || !file_exists($path)) {
            return null;
        }

        return [
            'attachment_id' => $attachmentId,
            'url'           => $this->applyBaseUrl($attachmentUrl),
            'path'          => $path,
            'alt'           => (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true),
            'mime_type'     => (string) get_post_mime_type($attachmentId),
            'filename'      => wp_basename($path),
        ];
    }

    /**
     * Download the image body.
     *
     * @return string|WP_Error Raw image bytes.
     */
    private function fetch(string $url): string|WP_Error
    {
        $maxBytes = self::maxBytes();

        $args = [
            /** This filter is documented in src/ImageImporter.php */
            'timeout'            => (int) apply_filters('external_image_importer_request_timeout', 15),
            'redirection'        => 3,
            'limit_response_size' => $maxBytes + 1,
            'user-agent'         => sprintf('Mozilla/5.0 (compatible; ExternalImageImporter/%s; +%s)', VERSION, home_url('/')),
            'headers'            => ['Accept' => 'image/*'],
        ];

        /**
         * Filters whether requests to private/reserved IP ranges are allowed.
         *
         * Leave this false unless you deliberately import from an internal
         * host: allowing it re-opens server side request forgery.
         *
         * @param bool   $allow Whether to skip WordPress' safe-URL checks.
         * @param string $url   URL being fetched.
         */
        $allowUnsafe = (bool) apply_filters('external_image_importer_allow_unsafe_urls', false, $url);

        $response = $allowUnsafe
            ? wp_remote_get($url, $args)
            : wp_safe_remote_get($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return new WP_Error(
                'eximgimp_bad_response',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __('The remote server answered with HTTP %d.', 'external-image-importer'),
                    $code
                )
            );
        }

        $body = (string) wp_remote_retrieve_body($response);

        if ($body === '') {
            return new WP_Error('eximgimp_empty_body', __('The remote server returned an empty response.', 'external-image-importer'));
        }

        if (strlen($body) > $maxBytes) {
            return new WP_Error(
                'eximgimp_too_large',
                sprintf(
                    /* translators: %d: maximum size in megabytes */
                    __('The image is larger than the %d MB limit.', 'external-image-importer'),
                    (int) round($maxBytes / MB_IN_BYTES)
                )
            );
        }

        return $body;
    }

    /**
     * Maximum number of bytes a single image may occupy.
     */
    public static function maxBytes(): int
    {
        $megabytes = (int) Settings::get('max_file_size', 25);
        $megabytes = $megabytes > 0 ? $megabytes : 25;

        /**
         * Filters the maximum download size, in bytes.
         *
         * @param int $bytes Maximum size.
         */
        return (int) apply_filters('external_image_importer_max_bytes', $megabytes * MB_IN_BYTES);
    }

    /**
     * Determine the real mime type of downloaded bytes.
     *
     * The type is sniffed from the content, never taken from the URL or the
     * Content-Type header, both of which the remote server controls.
     */
    public static function detectMimeType(string $body): ?string
    {
        if (function_exists('getimagesizefromstring')) {
            $info = @getimagesizefromstring($body); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

            if (is_array($info) && !empty($info['mime']) && is_string($info['mime'])) {
                return strtolower($info['mime']);
            }
        }

        // getimagesizefromstring() does not know AVIF on every PHP build.
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $mime = finfo_buffer($finfo, $body);
                finfo_close($finfo);

                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        return null;
    }

    /**
     * Build the stored filename from the user's pattern.
     */
    private function buildFilename(string $url, string $extension): string
    {
        $resolver = new PatternResolver($this->post, $this->alt, self::remoteFilename($url));
        $name     = sanitize_file_name(trim($resolver->resolve((string) Settings::get('image_name'))));

        // sanitize_file_name() can legitimately empty a pattern (for example
        // "%image_alt%" on an image with no alt text).
        if ($name === '' || trim($name, '.') === '') {
            $name = uniqid('img_', false);
        }

        return $name . '.' . $extension;
    }

    /**
     * The remote filename, without extension, when the URL looks like a file.
     */
    public static function remoteFilename(string $url): ?string
    {
        $path = Url::path($url);

        if ($path === '') {
            return null;
        }

        $path      = urldecode($path);
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($extension, self::FILENAME_EXTENSIONS, true)) {
            return null;
        }

        $filename = sanitize_file_name((string) pathinfo($path, PATHINFO_FILENAME));

        return $filename !== '' ? $filename : null;
    }

    /**
     * The alt text to store, after resolving the configured pattern.
     */
    private function resolvedAlt(string $url): string
    {
        $resolver = new PatternResolver($this->post, $this->alt, self::remoteFilename($url));

        return trim($resolver->resolve((string) Settings::get('alt_name')));
    }

    /**
     * Date used to pick the uploads sub-directory, so images land next to the
     * post they belong to.
     */
    private function uploadTime(): ?string
    {
        $date = $this->post['post_date'] ?? null;

        if (is_string($date) && $date !== '' && !str_starts_with($date, '0000-00-00')) {
            return $date;
        }

        return null;
    }

    /**
     * Shrink the stored file when a maximum size is configured.
     */
    private function maybeResize(string $path): void
    {
        $maxWidth  = (int) Settings::get('max_width', 0);
        $maxHeight = (int) Settings::get('max_height', 0);

        if ($maxWidth <= 0 && $maxHeight <= 0) {
            return;
        }

        $editor = wp_get_image_editor($path);

        if (is_wp_error($editor)) {
            return;
        }

        $resized = $editor->resize($maxWidth ?: null, $maxHeight ?: null, false);

        if (is_wp_error($resized)) {
            return;
        }

        // Saving over the original keeps a single file and a stable URL.
        $editor->save($path);
    }

    /**
     * Create the attachment post and generate its metadata.
     *
     * @return int|WP_Error Attachment ID.
     */
    private function attach(string $path, string $url, string $mime, string $filename, string $alt): int|WP_Error
    {
        $title = $alt !== '' ? $alt : (string) preg_replace('/\.[^.]+$/', '', $filename);

        $attachmentId = wp_insert_attachment(
            [
                'guid'           => $url,
                'post_mime_type' => $mime,
                'post_title'     => sanitize_text_field($title),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ],
            $path,
            (int) ($this->post['ID'] ?? 0),
            true
        );

        if (is_wp_error($attachmentId)) {
            return $attachmentId;
        }

        $attachmentId = (int) $attachmentId;

        if ($attachmentId === 0) {
            return new WP_Error('eximgimp_attachment_failed', __('The attachment could not be created.', 'external-image-importer'));
        }

        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $metadata = wp_generate_attachment_metadata($attachmentId, $path);

        if (is_array($metadata)) {
            wp_update_attachment_metadata($attachmentId, $metadata);
        }

        if ($alt !== '') {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', $alt);
        }

        return $attachmentId;
    }

    /**
     * Rewrite an uploads URL onto the configured base URL (e.g. a CDN).
     */
    private function applyBaseUrl(string $uploadUrl): string
    {
        $base = trim((string) Settings::get('base_url'));

        if ($base === '' || $base === home_url()) {
            return $uploadUrl;
        }

        $path = Url::path($uploadUrl);

        if ($path === '') {
            return $uploadUrl;
        }

        if ($base === '/') {
            return $path;
        }

        if (Url::host($base) === null) {
            return $uploadUrl;
        }

        return rtrim($base, '/') . $path;
    }
}
