<?php

declare(strict_types=1);

namespace ExternalImageImporter\Tests\Unit;

use ExternalImageImporter\ContentParser;
use PHPUnit\Framework\TestCase;

final class ContentParserTest extends TestCase
{
    private ContentParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new ContentParser();
    }

    public function testReturnsNothingForContentWithoutImages(): void
    {
        $this->assertSame([], $this->parser->parse(''));
        $this->assertSame([], $this->parser->parse('Just some text.'));
    }

    public function testFindsSrcAndAlt(): void
    {
        $content = '<img src="https://irani.im/images/ali.jpg" alt="Ali Irani" />';

        $tags = $this->parser->parse($content);

        $this->assertCount(1, $tags);
        $this->assertSame(['https://irani.im/images/ali.jpg'], $tags[0]->urls);
        $this->assertSame('Ali Irani', $tags[0]->alt);
    }

    public function testFindsSrcsetCandidatesWithoutDescriptors(): void
    {
        $content = '<img src="https://irani.im/w800/a.jpg" srcset="https://irani.im/w600/a.jpg 600w, https://irani.im/w300/a.jpg 300w" alt="">';

        $tags = $this->parser->parse($content);

        $this->assertCount(1, $tags);
        $this->assertSame(
            [
                'https://irani.im/w800/a.jpg',
                'https://irani.im/w600/a.jpg',
                'https://irani.im/w300/a.jpg',
            ],
            $tags[0]->urls
        );
    }

    public function testFindsSourceElementsInsidePicture(): void
    {
        $content = '<picture><source srcset="https://irani.im/a.webp" type="image/webp"><img src="https://irani.im/a.jpg" alt="a"></picture>';

        $tags = $this->parser->parse($content);

        $this->assertCount(2, $tags);
        $this->assertSame(['https://irani.im/a.webp'], $tags[0]->urls);
        $this->assertSame(['https://irani.im/a.jpg'], $tags[1]->urls);
    }

    public function testSkipsNonFetchableUrls(): void
    {
        $content = '<img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="inline">'
            . '<img src="/wp-content/uploads/local.jpg" alt="local">'
            . '<img src="https://irani.im/remote.jpg" alt="remote">';

        $tags = $this->parser->parse($content);

        $this->assertCount(1, $tags);
        $this->assertSame(['https://irani.im/remote.jpg'], $tags[0]->urls);
    }

    public function testDeduplicatesIdenticalTags(): void
    {
        $tag     = '<img src="https://irani.im/a.jpg" alt="a">';
        $content = $tag . ' text ' . $tag;

        $this->assertCount(1, $this->parser->parse($content));
    }

    public function testHandlesSingleQuotedAndUnquotedAttributes(): void
    {
        $content = "<img src='https://irani.im/a.jpg' alt='single'>"
            . '<img src=https://irani.im/b.jpg alt=unquoted>';

        $tags = $this->parser->parse($content);

        $this->assertCount(2, $tags);
        $this->assertSame('single', $tags[0]->alt);
        $this->assertSame(['https://irani.im/b.jpg'], $tags[1]->urls);
    }

    public function testAttributeReturnsNullWhenMissing(): void
    {
        $this->assertNull(ContentParser::attribute('<img src="a.jpg">', 'alt'));
        $this->assertSame('', ContentParser::attribute('<img src="a.jpg" alt="">', 'alt'));
    }
}
