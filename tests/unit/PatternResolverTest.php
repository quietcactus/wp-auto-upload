<?php

declare(strict_types=1);

namespace ExternalImageImporter\Tests\Unit;

use ExternalImageImporter\PatternResolver;
use PHPUnit\Framework\TestCase;

final class PatternResolverTest extends TestCase
{
    private const POST = [
        'ID'            => 42,
        'post_name'     => 'sample-post',
        'post_date'     => '2021-03-05 10:00:00',
        'post_date_gmt' => '2021-03-05 10:00:00',
    ];

    private function resolver(?string $alt = 'sample alt', ?string $source = 'ali-irani'): PatternResolver
    {
        return new PatternResolver(self::POST, $alt, $source);
    }

    public function testResolvesImageTokens(): void
    {
        $resolver = $this->resolver();

        $this->assertSame('ali-irani', $resolver->resolve('%filename%'));
        $this->assertSame('sample alt', $resolver->resolve('%image_alt%'));
        $this->assertSame('42', $resolver->resolve('%post_id%'));
        $this->assertSame('sample-post', $resolver->resolve('%postname%'));
        $this->assertSame('example.com', $resolver->resolve('%url%'));
    }

    public function testResolvesPostDateTokens(): void
    {
        $resolver = $this->resolver();

        $this->assertSame('2021-03-5', $resolver->resolve('%post_date%'));
        $this->assertSame('2021', $resolver->resolve('%post_year%'));
        $this->assertSame('03', $resolver->resolve('%post_month%'));
        $this->assertSame('5', $resolver->resolve('%post_day%'));
    }

    public function testResolvesTodayTokens(): void
    {
        $resolver = $this->resolver();

        $this->assertSame(gmdate('Y'), $resolver->resolve('%year%'));
        $this->assertSame(gmdate('m'), $resolver->resolve('%month%'));
        $this->assertSame(gmdate('j'), $resolver->resolve('%today_day%'));
        $this->assertSame(gmdate('Y-m-j'), $resolver->resolve('%today_date%'));
    }

    public function testDeprecatedTokensStillWork(): void
    {
        $resolver = $this->resolver();

        $this->assertSame(gmdate('Y-m-j'), $resolver->resolve('%date%'));
        $this->assertSame(gmdate('j'), $resolver->resolve('%day%'));
        $this->assertSame('%today_date%', PatternResolver::upgradeDeprecatedTokens('%date%'));
    }

    public function testUnknownTokensArePreserved(): void
    {
        $this->assertSame('%nope%', $this->resolver()->resolve('%nope%'));
    }

    public function testCombinedPattern(): void
    {
        $this->assertSame('2021-42-ali-irani', $this->resolver()->resolve('%post_year%-%post_id%-%filename%'));
    }

    public function testMissingPostDateFallsBackToToday(): void
    {
        $resolver = new PatternResolver(['ID' => 1, 'post_date' => '0000-00-00 00:00:00'], null, null);

        $this->assertSame(gmdate('Y'), $resolver->resolve('%post_year%'));
    }

    /**
     * A brand new post has no ID yet, so %post_id% must resolve to nothing
     * rather than to a literal "0".
     */
    public function testPostIdResolvesToNothingBeforeThePostExists(): void
    {
        $this->assertSame('', (new PatternResolver(['ID' => 0], null, null))->resolve('%post_id%'));
        $this->assertSame('', (new PatternResolver([], null, null))->resolve('%post_id%'));
        $this->assertSame('-photo', (new PatternResolver(['ID' => 0], null, 'photo'))->resolve('%post_id%-%filename%'));
    }

    public function testNullPatternResolvesToEmptyString(): void
    {
        $this->assertSame('', $this->resolver()->resolve(null));
    }

    /**
     * A pattern is user input; it must never be treated as a regular expression.
     */
    public function testPatternIsNotInterpretedAsRegex(): void
    {
        $resolver = $this->resolver(alt: 'a.b(c)');

        $this->assertSame('a.b(c)', $resolver->resolve('%image_alt%'));
        $this->assertSame('.*+?', $resolver->resolve('.*+?'));
    }

    /**
     * A "$1" inside a replacement value must survive verbatim.
     */
    public function testDollarSignsInValuesAreLiteral(): void
    {
        $resolver = $this->resolver(alt: 'cost $1 and $0');

        $this->assertSame('cost $1 and $0', $resolver->resolve('%image_alt%'));
    }
}
