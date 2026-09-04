<?php

declare(strict_types=1);

namespace ExternalImageImporter\Tests\Unit;

use ExternalImageImporter\ImageTag;
use PHPUnit\Framework\TestCase;

final class ImageTagTest extends TestCase
{
    public function testReplaceUrl(): void
    {
        $tag = new ImageTag('<img src="https://a.test/x.jpg" alt="x">', ['https://a.test/x.jpg'], 'x');

        $tag->replaceUrl('https://a.test/x.jpg', 'https://mine.test/x.jpg');

        $this->assertSame('<img src="https://mine.test/x.jpg" alt="x">', $tag->rewritten());
        $this->assertTrue($tag->isModified());
    }

    public function testSetAltReplacesExistingAttribute(): void
    {
        $tag = new ImageTag('<img src="https://a.test/x.jpg" alt="old">', ['https://a.test/x.jpg'], 'old');

        $tag->setAlt('new alt');

        $this->assertSame('<img src="https://a.test/x.jpg" alt="new alt">', $tag->rewritten());
    }

    public function testSetAltAddsAttributeWhenMissing(): void
    {
        $tag = new ImageTag('<img src="https://a.test/x.jpg">', ['https://a.test/x.jpg']);

        $tag->setAlt('added');

        $this->assertSame('<img alt="added" src="https://a.test/x.jpg">', $tag->rewritten());
    }

    public function testSetAltEscapesQuotes(): void
    {
        $tag = new ImageTag('<img src="https://a.test/x.jpg" alt="old">', ['https://a.test/x.jpg'], 'old');

        $tag->setAlt('say "hi" & <b>bye</b>');

        $this->assertStringNotContainsString('<b>', $tag->rewritten());
        $this->assertStringContainsString('&quot;hi&quot;', $tag->rewritten());
    }

    /**
     * A "$0" in the alt text must not be read as a regex backreference.
     */
    public function testSetAltTreatsDollarSignsLiterally(): void
    {
        $tag = new ImageTag('<img src="https://a.test/x.jpg" alt="old">', ['https://a.test/x.jpg'], 'old');

        $tag->setAlt('price $1 \\ $0');

        $this->assertSame('<img src="https://a.test/x.jpg" alt="price $1 \\ $0">', $tag->rewritten());
    }

    public function testSetAltIgnoresSourceElements(): void
    {
        $tag = new ImageTag('<source srcset="https://a.test/x.webp">', ['https://a.test/x.webp']);

        $tag->setAlt('nope');

        $this->assertSame('<source srcset="https://a.test/x.webp">', $tag->rewritten());
        $this->assertFalse($tag->isModified());
    }
}
