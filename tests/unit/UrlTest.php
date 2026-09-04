<?php

declare(strict_types=1);

namespace ExternalImageImporter\Tests\Unit;

use ExternalImageImporter\Url;
use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase
{
    public function testNormalizeExpandsProtocolRelativeUrls(): void
    {
        $this->assertSame('https://example.org/a.jpg', Url::normalize('//example.org/a.jpg'));
        $this->assertSame('https://example.org/a.jpg', Url::normalize('https://example.org/a.jpg'));
    }

    public function testNormalizeDecodesHtmlEntities(): void
    {
        $this->assertSame(
            'https://example.org/a.jpg?w=1&h=2',
            Url::normalize('https://example.org/a.jpg?w=1&amp;h=2')
        );
    }

    /**
     * @dataProvider unfetchableUrls
     */
    public function testIsFetchableRejectsNonHttpUrls(string $url): void
    {
        $this->assertFalse(Url::isFetchable($url));
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function unfetchableUrls(): array
    {
        return [
            ['data:image/png;base64,iVBORw0KGgo='],
            ['javascript:alert(1)'],
            ['/relative/image.jpg'],
            ['image.jpg'],
            ['ftp://example.org/a.jpg'],
            ['file:///etc/passwd'],
            [''],
        ];
    }

    public function testIsFetchableAcceptsHttpUrls(): void
    {
        $this->assertTrue(Url::isFetchable('http://example.org/a.jpg'));
        $this->assertTrue(Url::isFetchable('https://example.org/a.jpg'));
        $this->assertTrue(Url::isFetchable('//example.org/a.jpg'));
    }

    public function testHostStripsWww(): void
    {
        $this->assertSame('irani.im', Url::host('https://www.irani.im/test'));
        $this->assertSame('irani.im', Url::host('https://www2.irani.im/test'));
        $this->assertSame('www.irani.im', Url::host('https://www.irani.im/test', true));
    }

    public function testHostKeepsPortAndLowercases(): void
    {
        $this->assertSame('example.org:8080', Url::host('https://Example.ORG:8080/a.jpg'));
    }

    public function testHostAcceptsBareHostnames(): void
    {
        $this->assertSame('example.org', Url::host('example.org'));
        $this->assertSame('example.org', Url::host('  example.org  '));
    }

    public function testHostReturnsNullWithoutHost(): void
    {
        $this->assertNull(Url::host(null));
        $this->assertNull(Url::host(''));
    }

    public function testOrigin(): void
    {
        $this->assertSame('https://www.irani.im', Url::origin('https://www.irani.im/test'));
        $this->assertSame('http://irani.im', Url::origin('http://irani.im/test'));
        $this->assertNull(Url::origin(null));
    }

    public function testPath(): void
    {
        $this->assertSame('/wp-content/uploads/2024/01/a.jpg', Url::path('https://example.org/wp-content/uploads/2024/01/a.jpg?x=1'));
        $this->assertSame('', Url::path('https://example.org'));
    }
}
