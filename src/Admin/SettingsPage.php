<?php
/**
 * Settings screen under Settings -> External Image Importer.
 *
 * @package ExternalImageImporter
 */

declare(strict_types=1);

namespace ExternalImageImporter\Admin;

use ExternalImageImporter\Settings;

use const ExternalImageImporter\PLUGIN_BASENAME;
use const ExternalImageImporter\PLUGIN_DIR;

defined('ABSPATH') || exit;

final class SettingsPage
{
    /**
     * Capability required to view or change the settings.
     */
    public const CAPABILITY = 'manage_options';

    public const MENU_SLUG = 'external-image-importer';

    private const NONCE_ACTION = 'eximgimp_save_settings';

    /**
     * Hook the screen into the admin.
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_filter('plugin_action_links_' . PLUGIN_BASENAME, [$this, 'addActionLinks']);
    }

    /**
     * Add the options page and its form handler.
     */
    public function addMenu(): void
    {
        $hook = add_options_page(
            __('External Image Importer Settings', 'external-image-importer'),
            __('External Image Importer', 'external-image-importer'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'render']
        );

        if (is_string($hook) && $hook !== '') {
            add_action('load-' . $hook, [$this, 'handleSubmit']);
        }
    }

    /**
     * Add a "Settings" link to the plugin row.
     *
     * @param array<int, string> $links Existing links.
     *
     * @return array<int, string>
     */
    public function addActionLinks(array $links): array
    {
        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('options-general.php?page=' . self::MENU_SLUG)),
                esc_html__('Settings', 'external-image-importer')
            )
        );

        return $links;
    }

    /**
     * Validate and store a submitted form, then redirect (POST/redirect/GET).
     */
    public function handleSubmit(): void
    {
        if (!isset($_POST['eximgimp_submit']) && !isset($_POST['eximgimp_reset'])) {
            return;
        }

        if (!current_user_can(self::CAPABILITY)) {
            wp_die(
                esc_html__('You do not have permission to change these settings.', 'external-image-importer'),
                403
            );
        }

        // Verifies the nonce for both the save and the reset button.
        check_admin_referer(self::NONCE_ACTION);

        if (isset($_POST['eximgimp_reset'])) {
            Settings::reset();
            $notice = 'reset';
        } else {
            // Every value is validated and escaped inside Settings::sanitize().
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            Settings::update((array) $_POST);
            $notice = 'saved';
        }

        wp_safe_redirect(
            add_query_arg(
                ['page' => self::MENU_SLUG, 'eximgimp-notice' => $notice],
                admin_url('options-general.php')
            )
        );

        exit;
    }

    /**
     * Render the settings screen.
     */
    public function render(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(
                esc_html__('You do not have permission to view these settings.', 'external-image-importer'),
                403
            );
        }

        $options    = Settings::all();
        $nonce      = self::NONCE_ACTION;
        $noticeCode = isset($_GET['eximgimp-notice']) ? sanitize_key(wp_unslash($_GET['eximgimp-notice'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $notice = match ($noticeCode) {
            'saved' => __('Settings saved.', 'external-image-importer'),
            'reset' => __('Settings have been reset to their defaults.', 'external-image-importer'),
            default => '',
        };

        require PLUGIN_DIR . 'src/views/settings-page.php';
    }
}
