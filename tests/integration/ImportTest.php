<?php

declare(strict_types=1);

namespace ExternalImageImporter\Tests\Integration;

use ExternalImageImporter\ImageImporter;
use ExternalImageImporter\Settings;
use WP_UnitTestCase;

/**
 * End-to-end tests for the save-post import pass.
 *
 * All HTTP traffic is intercepted with `pre_http_request`, so the suite never
 * touches the network.
 */
final class ImportTest extends WP_UnitTestCase
{
    /**
     * 1x1 transparent PNG.
     */
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * URLs that were actually requested during a test.
     *
     * @var array<int, string>
     */
    private array $requested = [];

    public function set_up(): void
    {
        parent::set_up();

        Settings::flushCache();
        Settings::reset();

        $this->requested = [];

        add_filter('pre_http_request', [$this, 'mockHttp'], 10, 3);
    }

    public function tear_down(): void
    {
        remove_filter('pre_http_request', [$this, 'mockHttp'], 10);

        parent::tear_down();
    }

    /**
     * Serve a real PNG for *.png / *.jpg, and HTML for anything else.
     *
     * @param mixed                $preempt Short-circuit value.
     * @param array<string, mixed> $args    Request arguments.
     * @param string               $url     Requested URL.
     *
     * @return array<string, mixed>
     */
    public function mockHttp($preempt, array $args, string $url): array
    {
        $this->requested[] = $url;

        $isImage = (bool) preg_match('/\.(png|jpe?g|gif|webp)(\?|$)/i', $url);

        return [
            'headers'  => [],
            'cookies'  => [],
            'filename' => null,
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => $isImage
                ? (string) base64_decode(self::PNG_BASE64, true)
                : '<!doctype html><html><body>not an image</body></html>',
        ];
    }

    private function createPost(string $content): \WP_Post
    {
        $postId = self::factory()->post->create(['post_content' => $content]);

        $post = get_post($postId);

        $this->assertInstanceOf(\WP_Post::class, $post);

        return $post;
    }

    public function testExternalImageIsImportedAndUrlRewritten(): void
    {
        $post = $this->createPost('<img src="https://remote.test/photo.png" alt="A photo">');

        $this->assertStringNotContainsString('remote.test', $post->post_content);
        $this->assertMatchesRegularExpression(
            '#http://example\.org/wp-content/uploads/\d{4}/\d{2}/[^"]+\.png#',
            $post->post_content
        );
    }

    public function testAttachmentIsCreatedAndTaggedWithItsSource(): void
    {
        $this->createPost('<img src="https://remote.test/photo.png" alt="A photo">');

        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        $this->assertCount(1, $attachments);
        $this->assertSame(
            'https://remote.test/photo.png',
            get_post_meta((int) $attachments[0], ImageImporter::SOURCE_META_KEY, true)
        );
        $this->assertSame('A photo', get_post_meta((int) $attachments[0], '_wp_attachment_image_alt', true));
    }

    public function testTheSameSourceIsOnlyDownloadedOnce(): void
    {
        $this->createPost('<img src="https://remote.test/photo.png" alt="one">');
        $this->createPost('<img src="https://remote.test/photo.png" alt="two">');

        $this->assertCount(1, $this->requested);

        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        $this->assertCount(1, $attachments, 'A second save must reuse the existing attachment.');
    }

    public function testSrcsetCandidatesAreImported(): void
    {
        $post = $this->createPost(
            '<img src="https://remote.test/large.png" srcset="https://remote.test/small.png 300w, https://remote.test/large.png 800w" alt="responsive">'
        );

        $this->assertStringNotContainsString('remote.test', $post->post_content);
        $this->assertCount(2, array_unique($this->requested));
    }

    public function testLocalImagesAreLeftAlone(): void
    {
        $content = '<img src="' . home_url('/wp-content/uploads/2020/01/local.png') . '" alt="local">';

        $post = $this->createPost($content);

        $this->assertSame($content, $post->post_content);
        $this->assertSame([], $this->requested);
    }

    public function testExcludedDomainsAreSkipped(): void
    {
        Settings::update(['exclude_urls' => "remote.test\n"]);

        $content = '<img src="https://remote.test/photo.png" alt="skip me">';
        $post    = $this->createPost($content);

        $this->assertSame($content, $post->post_content);
        $this->assertSame([], $this->requested);
    }

    public function testExcludedPostTypesAreSkipped(): void
    {
        Settings::update(['exclude_post_types' => ['post']]);

        $content = '<img src="https://remote.test/photo.png" alt="skip me">';
        $post    = $this->createPost($content);

        $this->assertSame($content, $post->post_content);
        $this->assertSame([], $this->requested);
    }

    public function testBaseUrlOverrideIsApplied(): void
    {
        Settings::update(['base_url' => 'https://cdn.example.net']);

        $post = $this->createPost('<img src="https://remote.test/photo.png" alt="cdn">');

        $this->assertStringContainsString('https://cdn.example.net/wp-content/uploads/', $post->post_content);
    }

    public function testFilenamePatternIsApplied(): void
    {
        Settings::update(['image_name' => '%post_id%-%filename%']);

        $post = $this->createPost('<img src="https://remote.test/photo.png" alt="named">');

        $this->assertMatchesRegularExpression('#/\d+-photo\.png#', $post->post_content);
    }

    public function testAltPatternIsWrittenBackIntoTheContent(): void
    {
        Settings::update(['alt_name' => 'Photo of %postname%']);

        $post = $this->createPost('<img src="https://remote.test/photo.png" alt="old alt">');

        $this->assertStringContainsString('alt="Photo of ', $post->post_content);
        $this->assertStringNotContainsString('old alt', $post->post_content);
    }

    public function testNonImageResponsesAreRejected(): void
    {
        $content = '<img src="https://remote.test/not-an-image.php" alt="bad">';
        $post    = $this->createPost($content);

        $this->assertSame($content, $post->post_content, 'A HTML response must not be stored as an image.');
        $this->assertCount(
            0,
            get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids'])
        );
    }

    public function testRevisionsAndAutosavesAreIgnored(): void
    {
        $post = $this->createPost('<img src="https://remote.test/photo.png" alt="rev">');

        $requestsAfterFirstSave = count($this->requested);

        wp_save_post_revision($post->ID);

        $this->assertCount($requestsAfterFirstSave, $this->requested);
    }
}
