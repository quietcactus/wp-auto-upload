<?php

declare(strict_types=1);

namespace ExternalImageImporter\Tests\Unit;

use ExternalImageImporter\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsSanitizeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['eximgimp_test_options'] = [];
        Settings::flushCache();
    }

    public function testEmptyInputFallsBackToDefaults(): void
    {
        $clean = Settings::sanitize([]);

        $this->assertSame('https://example.com', $clean['base_url']);
        $this->assertSame('%filename%', $clean['image_name']);
        $this->assertSame('%image_alt%', $clean['alt_name']);
        $this->assertSame(0, $clean['max_width']);
        $this->assertSame(0, $clean['max_height']);
        $this->assertSame(25, $clean['max_file_size']);
        $this->assertSame('', $clean['exclude_urls']);
        $this->assertSame([], $clean['exclude_post_types']);
    }

    public function testRejectsNonHttpBaseUrl(): void
    {
        $clean = Settings::sanitize(['base_url' => 'javascript:alert(1)']);

        $this->assertSame('https://example.com', $clean['base_url']);
    }

    public function testAcceptsRootBaseUrl(): void
    {
        $this->assertSame('/', Settings::sanitize(['base_url' => '/'])['base_url']);
    }

    public function testStripsMarkupFromPatterns(): void
    {
        $clean = Settings::sanitize(['image_name' => '<script>alert(1)</script>%filename%']);

        $this->assertStringNotContainsString('<script>', $clean['image_name']);
        $this->assertStringContainsString('%filename%', $clean['image_name']);
    }

    public function testUpgradesDeprecatedPatternTokens(): void
    {
        $clean = Settings::sanitize(['image_name' => '%date%-%day%-%filename%']);

        $this->assertSame('%today_date%-%today_day%-%filename%', $clean['image_name']);
    }

    public function testDimensionsAreCastToNonNegativeIntegers(): void
    {
        $clean = Settings::sanitize(['max_width' => '-800abc', 'max_height' => '600']);

        $this->assertSame(800, $clean['max_width']);
        $this->assertSame(600, $clean['max_height']);
    }

    public function testMaxFileSizeIsCapped(): void
    {
        $this->assertSame(512, Settings::sanitize(['max_file_size' => 99999])['max_file_size']);
        $this->assertSame(25, Settings::sanitize(['max_file_size' => 0])['max_file_size']);
        $this->assertSame(5, Settings::sanitize(['max_file_size' => '5'])['max_file_size']);
    }

    public function testExcludeUrlsAreNormalisedToUniqueHosts(): void
    {
        $clean = Settings::sanitize([
            'exclude_urls' => "https://www.example.org/path\n\nexample.org\n  https://other.test  \n",
        ]);

        $this->assertSame("example.org\nother.test", $clean['exclude_urls']);
    }

    public function testOnlyRegisteredPostTypesAreStored(): void
    {
        $clean = Settings::sanitize([
            'exclude_post_types' => ['post', 'does-not-exist', 'page', 'post', ['nested']],
        ]);

        $this->assertSame(['post', 'page'], $clean['exclude_post_types']);
    }

    public function testUncheckingEveryPostTypeClearsTheList(): void
    {
        Settings::update(['exclude_post_types' => ['post']]);
        $this->assertSame(['post'], Settings::get('exclude_post_types'));

        Settings::update([]);
        $this->assertSame([], Settings::get('exclude_post_types'));
    }

    public function testUpdateStoresTheFullOptionSet(): void
    {
        Settings::update(['max_width' => 120]);

        $stored = $GLOBALS['eximgimp_test_options'][Settings::OPTION_KEY];

        $this->assertSame(120, $stored['max_width']);
        $this->assertArrayHasKey('base_url', $stored);
        $this->assertArrayHasKey('image_name', $stored);
    }

    public function testResetRestoresDefaults(): void
    {
        Settings::update(['max_width' => 120]);
        Settings::reset();

        $this->assertSame(0, Settings::get('max_width'));
    }

    public function testLegacyOptionsAreMigratedOnce(): void
    {
        $GLOBALS['eximgimp_test_options'][Settings::LEGACY_OPTION_KEY] = [
            'base_url'   => 'https://cdn.example.com',
            'image_name' => '%year%-%filename%',
        ];

        Settings::migrateLegacyOptions();

        $this->assertSame('https://cdn.example.com', Settings::get('base_url'));
        $this->assertSame('%year%-%filename%', Settings::get('image_name'));
    }
}
