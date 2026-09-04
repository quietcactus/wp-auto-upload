<?php
/**
 * Minimal WordPress function stubs so the framework-independent parts of the
 * plugin can be unit tested without a WordPress install.
 *
 * These are deliberately simple: they mirror just enough of core's behaviour
 * for the units under test. Anything that really needs WordPress belongs in
 * the integration suite under tests/integration.
 *
 * @package ExternalImageImporter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('MB_IN_BYTES')) {
    define('MB_IN_BYTES', 1024 * 1024);
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1)
    {
        return parse_url($url, $component);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('absint')) {
    function absint($value): int
    {
        return abs((int) $value);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        $value = strip_tags($value);
        $value = (string) preg_replace('/[\r\n\t ]+/', ' ', $value);

        return trim($value);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name(string $filename): string
    {
        $special = ['?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', chr(0)];

        $filename = str_replace($special, '', $filename);
        $filename = (string) preg_replace('/[\r\n\t -]+/', '-', $filename);

        return trim($filename, '.-_');
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url, array $protocols = ['http', 'https'])
    {
        $url    = trim($url);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (!is_string($scheme) || !in_array(strtolower($scheme), $protocols, true)) {
            return '';
        }

        return $url;
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://example.com' . $path;
    }
}

if (!function_exists('site_url')) {
    function site_url(string $path = ''): string
    {
        return 'https://example.com' . $path;
    }
}

if (!function_exists('get_option')) {
    function get_option(string $key, $default = false)
    {
        return $GLOBALS['eximgimp_test_options'][$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $key, $value): bool
    {
        $GLOBALS['eximgimp_test_options'][$key] = $value;

        return true;
    }
}

if (!function_exists('get_post_types')) {
    function get_post_types(array $args = [], string $output = 'names'): array
    {
        return [
            'post'       => 'post',
            'page'       => 'page',
            'attachment' => 'attachment',
            'product'    => 'product',
        ];
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value, ...$args)
    {
        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook, ...$args): void
    {
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
