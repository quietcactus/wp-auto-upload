<?php
/**
 * Expands the %token% placeholders used for filenames and alt attributes.
 *
 * @package ExternalImageImporter
 */

declare(strict_types=1);

namespace ExternalImageImporter;

defined('ABSPATH') || exit;

final class PatternResolver
{
    /**
     * Tokens that were renamed in v3.3.2 and are still accepted for
     * backwards compatibility.
     *
     * @var array<string, string>
     */
    private const DEPRECATED_TOKENS = [
        '%date%' => '%today_date%',
        '%day%'  => '%today_day%',
    ];

    /**
     * @param array<string, mixed> $post   Post data being saved.
     * @param string|null          $alt    Alt text of the image being imported.
     * @param string|null          $source Original filename of the remote image.
     */
    public function __construct(
        private array $post = [],
        private ?string $alt = null,
        private ?string $source = null
    ) {
    }

    /**
     * Replace every known token in `$pattern`.
     *
     * Unknown tokens are left untouched. Replacement uses strtr() rather than
     * preg_replace() so user supplied patterns can never be interpreted as a
     * regular expression or a backreference.
     */
    public function resolve(?string $pattern): string
    {
        $pattern = self::upgradeDeprecatedTokens((string) $pattern);

        if ($pattern === '') {
            return '';
        }

        return strtr($pattern, $this->tokens());
    }

    /**
     * Rewrite deprecated tokens to their modern equivalent.
     */
    public static function upgradeDeprecatedTokens(string $pattern): string
    {
        return strtr($pattern, self::DEPRECATED_TOKENS);
    }

    /**
     * Every supported token and its value for the current post/image.
     *
     * @return array<string, string>
     */
    private function tokens(): array
    {
        $postTimestamp = $this->postTimestamp();

        $tokens = [
            '%filename%'   => (string) $this->source,
            '%image_alt%'  => (string) $this->alt,
            '%today_date%' => self::formatDate('Y-m-j'),
            '%year%'       => self::formatDate('Y'),
            '%month%'      => self::formatDate('m'),
            '%today_day%'  => self::formatDate('j'),
            '%post_date%'  => self::formatDate('Y-m-j', $postTimestamp),
            '%post_year%'  => self::formatDate('Y', $postTimestamp),
            '%post_month%' => self::formatDate('m', $postTimestamp),
            '%post_day%'   => self::formatDate('j', $postTimestamp),
            '%url%'        => (string) Url::host(home_url()),
            '%random%'     => uniqid('img_', false),
            '%timestamp%'  => (string) time(),
            '%post_id%'    => empty($this->post['ID']) ? '' : (string) $this->post['ID'],
            '%postname%'   => (string) ($this->post['post_name'] ?? ''),
        ];

        /**
         * Filters the tokens available to filename and alt patterns.
         *
         * @param array<string, string> $tokens Token => replacement map.
         * @param array<string, mixed>  $post   Post data being saved.
         */
        return apply_filters('external_image_importer_pattern_tokens', $tokens, $this->post);
    }

    /**
     * Timestamp of the post being saved, or null when it has no usable date.
     */
    private function postTimestamp(): ?int
    {
        foreach (['post_date', 'post_date_gmt'] as $field) {
            $value = $this->post[$field] ?? null;

            if (!is_string($value) || $value === '' || str_starts_with($value, '0000-00-00')) {
                continue;
            }

            $timestamp = strtotime($value);

            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return null;
    }

    /**
     * Format a timestamp in the site timezone, falling back to UTC outside WP.
     */
    private static function formatDate(string $format, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        if (function_exists('wp_date')) {
            $formatted = wp_date($format, $timestamp);

            if (is_string($formatted)) {
                return $formatted;
            }
        }

        return gmdate($format, $timestamp);
    }
}
