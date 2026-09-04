<?php
/**
 * Small URL helpers shared by the parser and the importer.
 *
 * @package ExternalImageImporter
 */

declare(strict_types=1);

namespace ExternalImageImporter;

defined('ABSPATH') || exit;

final class Url
{
    /**
     * Schemes the importer is ever willing to fetch.
     */
    public const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * Turn a protocol-relative URL into an absolute https URL.
     *
     * Anything else is returned untouched so callers can reject it.
     */
    public static function normalize(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return $url;
    }

    /**
     * True when the URL is an absolute http(s) URL with a host.
     */
    public static function isFetchable(string $url): bool
    {
        $parts = wp_parse_url(self::normalize($url));

        if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return false;
        }

        return in_array(strtolower($parts['scheme']), self::ALLOWED_SCHEMES, true);
    }

    /**
     * Host (optionally with port) of a URL, with the "www." prefix removed.
     *
     * @return string|null Null when the URL carries no host.
     */
    public static function host(?string $url, bool $keepWww = false): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $normalized = self::normalize($url);
        $parts      = wp_parse_url($normalized);

        // Bare hostnames ("example.com") have no scheme, so parse them again.
        if (is_array($parts) && empty($parts['host']) && !empty($parts['path']) && empty($parts['scheme'])) {
            $parts = wp_parse_url('https://' . ltrim($normalized, '/'));
        }

        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);

        if (!empty($parts['port'])) {
            $host .= ':' . $parts['port'];
        }

        if (!$keepWww) {
            $host = (string) preg_replace('/^www[0-9]*\./i', '', $host);
        }

        return $host !== '' ? $host : null;
    }

    /**
     * Scheme + host of a URL, e.g. "https://example.com".
     *
     * @return string|null Null when the URL carries no host.
     */
    public static function origin(?string $url, bool $keepWww = true): ?string
    {
        if ($url === null) {
            return null;
        }

        $parts = wp_parse_url(self::normalize($url));
        $host  = self::host($url, $keepWww);

        if ($host === null) {
            return null;
        }

        $scheme = is_array($parts) && !empty($parts['scheme']) ? strtolower($parts['scheme']) : 'https';

        return $scheme . '://' . $host;
    }

    /**
     * Path component of a URL, or an empty string.
     */
    public static function path(string $url): string
    {
        $path = wp_parse_url(self::normalize($url), PHP_URL_PATH);

        return is_string($path) ? $path : '';
    }
}
