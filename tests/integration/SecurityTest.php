<?php

declare(strict_types=1);

namespace ExternalImageImporter\Tests\Integration;

use ExternalImageImporter\Admin\SettingsPage;
use ExternalImageImporter\ImageImporter;
use ExternalImageImporter\Settings;
use WP_UnitTestCase;

/**
 * Regression tests for the security fixes made in 4.0.0.
 */
final class SecurityTest extends WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        Settings::flushCache();
        Settings::reset();
    }

    /**
     * Requests must go through wp_safe_remote_get(), which refuses loopback
     * and private addresses. Without a pre_http_request mock in place this
     * never reaches the network.
     *
     * @dataProvider internalUrls
     */
    public function testServerSideRequestForgeryIsBlocked(string $url): void
    {
        $result = (new ImageImporter($url, null, ['ID' => 1]))->import();

        $this->assertWPError($result, $url . ' must be refused.');
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function internalUrls(): array
    {
        return [
            ['http://127.0.0.1/image.png'],
            ['http://localhost/image.png'],
            ['http://169.254.169.254/latest/meta-data/image.png'],
            ['http://10.0.0.1/image.png'],
            ['http://192.168.1.1/image.png'],
            ['http://[::1]/image.png'],
        ];
    }

    /**
     * @dataProvider nonHttpUrls
     */
    public function testNonHttpSchemesAreRefused(string $url): void
    {
        $result = (new ImageImporter($url, null, ['ID' => 1]))->import();

        $this->assertWPError($result);
        $this->assertSame('eximgimp_invalid_url', $result->get_error_code());
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function nonHttpUrls(): array
    {
        return [
            ['file:///etc/passwd'],
            ['ftp://example.org/a.jpg'],
            ['javascript:alert(1)'],
            ['data:image/png;base64,iVBORw0KGgo='],
        ];
    }

    /**
     * A response that is not really an image must never be written to uploads,
     * whatever the URL or the Content-Type header claims.
     */
    public function testExecutablePayloadDisguisedAsAnImageIsRejected(): void
    {
        $mock = static function ($preempt, array $args, string $url): array {
            return [
                'headers'  => ['content-type' => 'image/png'],
                'cookies'  => [],
                'filename' => null,
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => '<?php system($_GET["c"]); ?>',
            ];
        };

        add_filter('pre_http_request', $mock, 10, 3);

        $result = (new ImageImporter('https://remote.test/shell.png', null, ['ID' => 1]))->import();

        remove_filter('pre_http_request', $mock, 10);

        $this->assertWPError($result);
        $this->assertSame('eximgimp_unsupported_type', $result->get_error_code());
    }

    /**
     * SVG can carry script, so it is not an allowed type even though it is an
     * image format.
     */
    public function testSvgIsRejected(): void
    {
        $mock = static function ($preempt, array $args, string $url): array {
            return [
                'headers'  => ['content-type' => 'image/svg+xml'],
                'cookies'  => [],
                'filename' => null,
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            ];
        };

        add_filter('pre_http_request', $mock, 10, 3);

        $result = (new ImageImporter('https://remote.test/logo.svg', null, ['ID' => 1]))->import();

        remove_filter('pre_http_request', $mock, 10);

        $this->assertWPError($result);
        $this->assertSame('eximgimp_unsupported_type', $result->get_error_code());
    }

    public function testOversizedDownloadsAreRejected(): void
    {
        Settings::update(['max_file_size' => 1]);

        $mock = static function ($preempt, array $args, string $url): array {
            return [
                'headers'  => [],
                'cookies'  => [],
                'filename' => null,
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => str_repeat('A', 2 * MB_IN_BYTES),
            ];
        };

        add_filter('pre_http_request', $mock, 10, 3);

        $result = (new ImageImporter('https://remote.test/huge.png', null, ['ID' => 1]))->import();

        remove_filter('pre_http_request', $mock, 10);

        $this->assertWPError($result);
        $this->assertSame('eximgimp_too_large', $result->get_error_code());
    }

    public function testSettingsScreenRequiresManageOptions(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));

        $this->assertFalse(current_user_can(SettingsPage::CAPABILITY));
    }

    /**
     * The settings form is only processed when a valid nonce is present.
     */
    public function testSettingsAreNotSavedWithoutANonce(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $_POST = [
            'eximgimp_submit' => 'Save Changes',
            'base_url'   => 'https://evil.test',
        ];

        $failed = false;

        add_filter(
            'wp_die_handler',
            static function (): callable {
                return static function (): void {
                    throw new \RuntimeException('wp_die');
                };
            }
        );

        try {
            (new SettingsPage())->handleSubmit();
        } catch (\Throwable $e) {
            $failed = true;
        }

        $_POST = [];
        Settings::flushCache();

        $this->assertTrue($failed, 'A missing nonce must stop the request.');
        $this->assertNotSame('https://evil.test', Settings::get('base_url'));
    }
}
