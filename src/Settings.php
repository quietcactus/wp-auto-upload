<?php
/**
 * Plugin option storage, defaults, sanitisation and migration.
 *
 * @package ExternalImageImporter
 */

declare(strict_types=1);

namespace ExternalImageImporter;

defined('ABSPATH') || exit;

final class Settings
{
    /**
     * Option key used by this plugin.
     */
    public const OPTION_KEY = 'eximgimp_settings';

    /**
     * Option key used by the upstream "Auto Upload Images" plugin. Values are
     * migrated across once, so forked sites keep their configuration.
     */
    public const LEGACY_OPTION_KEY = 'aui-setting';

    /**
     * Cached, merged options for the current request.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $cache = null;

    /**
     * Default values for every supported option.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'base_url'           => home_url(),
            'image_name'         => '%filename%',
            'alt_name'           => '%image_alt%',
            'max_width'          => 0,
            'max_height'         => 0,
            'max_file_size'      => 25,
            'exclude_urls'       => '',
            'exclude_post_types' => [],
        ];
    }

    /**
     * All options, merged over the defaults.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $stored = get_option(self::OPTION_KEY, []);

        if (!is_array($stored)) {
            $stored = [];
        }

        return self::$cache = array_merge(self::defaults(), $stored);
    }

    /**
     * Read a single option.
     *
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $options = self::all();

        return array_key_exists($key, $options) ? $options[$key] : $default;
    }

    /**
     * Persist a full set of options. Values are sanitised before saving.
     *
     * @param array<string, mixed> $options Raw option values.
     */
    public static function update(array $options): bool
    {
        $clean = self::sanitize($options);

        self::$cache = array_merge(self::defaults(), $clean);

        return update_option(self::OPTION_KEY, $clean);
    }

    /**
     * Restore the default configuration.
     */
    public static function reset(): bool
    {
        $defaults = self::defaults();

        self::$cache = $defaults;

        return update_option(self::OPTION_KEY, $defaults);
    }

    /**
     * Drop the in-memory cache. Used by the test suite.
     */
    public static function flushCache(): void
    {
        self::$cache = null;
    }

    /**
     * Sanitise a raw option array coming from the settings form.
     *
     * Every value is validated against its expected type and range: nothing
     * from `$_POST` reaches the database untouched.
     *
     * @param array<string, mixed> $raw Raw values.
     *
     * @return array<string, mixed>
     */
    public static function sanitize(array $raw): array
    {
        $defaults = self::defaults();
        $clean    = [];

        $clean['base_url']   = self::sanitizeBaseUrl($raw['base_url'] ?? '', $defaults['base_url']);
        $clean['image_name'] = self::sanitizePattern($raw['image_name'] ?? '', $defaults['image_name']);
        $clean['alt_name']   = self::sanitizePattern($raw['alt_name'] ?? '', $defaults['alt_name']);

        // A zero disables the limit, so absint() is the correct floor here.
        $clean['max_width']  = absint($raw['max_width'] ?? 0);
        $clean['max_height'] = absint($raw['max_height'] ?? 0);

        $maxFileSize            = absint($raw['max_file_size'] ?? $defaults['max_file_size']);
        $clean['max_file_size'] = min($maxFileSize ?: $defaults['max_file_size'], 512);

        $clean['exclude_urls']       = self::sanitizeExcludeUrls($raw['exclude_urls'] ?? '');
        $clean['exclude_post_types'] = self::sanitizePostTypes($raw['exclude_post_types'] ?? []);

        return $clean;
    }

    /**
     * Validate the base URL. Only http(s) URLs and a bare "/" are accepted.
     */
    private static function sanitizeBaseUrl(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? trim(wp_unslash($value)) : '';

        if ($value === '') {
            return $fallback;
        }

        if ($value === '/') {
            return $value;
        }

        $url = esc_url_raw($value, ['http', 'https']);

        return $url !== '' ? $url : $fallback;
    }

    /**
     * Sanitise a filename/alt pattern such as "%year%-%filename%".
     */
    private static function sanitizePattern(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? sanitize_text_field(wp_unslash($value)) : '';
        $value = PatternResolver::upgradeDeprecatedTokens($value);

        return $value !== '' ? $value : $fallback;
    }

    /**
     * Normalise the excluded-domains textarea to one host per line.
     */
    private static function sanitizeExcludeUrls(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $lines = preg_split('/[\r\n]+/', sanitize_textarea_field(wp_unslash($value))) ?: [];
        $hosts = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $host = Url::host($line);
            $host = $host ?? sanitize_text_field($line);

            if ($host !== '' && !in_array($host, $hosts, true)) {
                $hosts[] = $host;
            }
        }

        return implode("\n", $hosts);
    }

    /**
     * Keep only post types that actually exist on this site.
     *
     * @param mixed $value Raw checkbox values.
     *
     * @return array<int, string>
     */
    private static function sanitizePostTypes(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $registered = get_post_types([], 'names');
        $clean      = [];

        foreach ($value as $postType) {
            if (!is_string($postType)) {
                continue;
            }

            $postType = sanitize_key(wp_unslash($postType));

            if (isset($registered[$postType]) && !in_array($postType, $clean, true)) {
                $clean[] = $postType;
            }
        }

        return $clean;
    }

    /**
     * Copy configuration from the upstream plugin the first time this fork runs.
     */
    public static function migrateLegacyOptions(): void
    {
        if (get_option(self::OPTION_KEY, null) !== null) {
            return;
        }

        $legacy = get_option(self::LEGACY_OPTION_KEY, null);

        if (!is_array($legacy)) {
            return;
        }

        self::update($legacy);
    }

    /**
     * Activation hook: seed defaults and pull across legacy configuration.
     */
    public static function onActivate(): void
    {
        self::migrateLegacyOptions();

        if (get_option(self::OPTION_KEY, null) === null) {
            update_option(self::OPTION_KEY, self::defaults());
        }
    }
}
