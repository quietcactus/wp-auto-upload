<?php

declare(strict_types=1);

namespace ExternalImageImporter\Tests\Unit;

use ExternalImageImporter\ImageImporter;
use PHPUnit\Framework\TestCase;

final class ImageImporterTest extends TestCase
{
    /**
     * Smallest possible valid GIF.
     */
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * 1x1 transparent PNG.
     */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function testDetectsRealImageTypes(): void
    {
        $this->assertSame('image/gif', ImageImporter::detectMimeType((string) base64_decode(self::GIF, true)));
        $this->assertSame('image/png', ImageImporter::detectMimeType((string) base64_decode(self::PNG, true)));
    }

    /**
     * The mime type is sniffed from the bytes, so a file that merely claims to
     * be an image is rejected.
     */
    public function testRejectsNonImagePayloads(): void
    {
        $php = ImageImporter::detectMimeType('<?php echo "pwned"; ?>');
        $svg = ImageImporter::detectMimeType('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->assertArrayNotHasKey((string) $php, ImageImporter::ALLOWED_MIME_TYPES);
        $this->assertArrayNotHasKey((string) $svg, ImageImporter::ALLOWED_MIME_TYPES);
    }

    public function testSvgIsNotAnAllowedType(): void
    {
        $this->assertArrayNotHasKey('image/svg+xml', ImageImporter::ALLOWED_MIME_TYPES);
    }

    /**
     * @dataProvider filenameUrls
     */
    public function testRemoteFilename(string $url, ?string $expected): void
    {
        $this->assertSame($expected, ImageImporter::remoteFilename($url));
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function filenameUrls(): array
    {
        return [
            'plain image'       => ['https://irani.im/images/ali-irani.jpg', 'ali-irani'],
            'query string'      => ['https://irani.im/images/ali-irani.jpg?a=b&d=c', 'ali-irani'],
            'uppercase ext'     => ['https://irani.im/images/Ali.PNG', 'Ali'],
            'script endpoint'   => ['https://irani.im/images/get.php?file=ali.jpg', null],
            'no extension'      => ['https://irani.im/images/ali', null],
            'directory only'    => ['https://irani.im/', null],
            'percent encoded'   => ['https://irani.im/images/my%20photo.jpg', 'my-photo'],
            'traversal attempt' => ['https://irani.im/images/..%2F..%2Fevil.jpg', 'evil'],
        ];
    }

    public function testMaxBytesHasASaneDefault(): void
    {
        $GLOBALS['eximgimp_test_options'] = [];
        \ExternalImageImporter\Settings::flushCache();

        $this->assertSame(25 * MB_IN_BYTES, ImageImporter::maxBytes());
    }
}
