<?php
/**
 * Finds image tags and their URLs inside post content.
 *
 * @package ExternalImageImporter
 */

declare(strict_types=1);

namespace ExternalImageImporter;

defined('ABSPATH') || exit;

final class ContentParser
{
    /**
     * Matches a complete <img> or <source> tag.
     */
    private const TAG_PATTERN = '/<(?:img|source)\b[^>]*>/i';

    /**
     * Collect every <img>/<source> tag that references at least one fetchable URL.
     *
     * Identical tags are returned once: rewriting is done with str_replace(),
     * which already updates every copy.
     *
     * @return array<int, ImageTag>
     */
    public function parse(string $content): array
    {
        if (trim($content) === '' || !preg_match_all(self::TAG_PATTERN, $content, $matches)) {
            return [];
        }

        $tags = [];
        $seen = [];

        foreach ($matches[0] as $html) {
            if (isset($seen[$html])) {
                continue;
            }

            $seen[$html] = true;

            $urls = $this->urlsIn($html);

            if ($urls === []) {
                continue;
            }

            $tags[] = new ImageTag($html, $urls, self::attribute($html, 'alt'));
        }

        return $tags;
    }

    /**
     * Every fetchable URL referenced by a tag's src and srcset attributes.
     *
     * URLs are returned exactly as written in the markup so they can be
     * str_replace()d back out of the content.
     *
     * @return array<int, string>
     */
    private function urlsIn(string $html): array
    {
        $candidates = [];

        $src = self::attribute($html, 'src');

        if ($src !== null) {
            $candidates[] = $src;
        }

        $srcset = self::attribute($html, 'srcset');

        if ($srcset !== null) {
            foreach (explode(',', $srcset) as $candidate) {
                $candidate = trim($candidate);

                if ($candidate === '') {
                    continue;
                }

                // "https://example.com/a.jpg 2x" -> "https://example.com/a.jpg"
                $candidates[] = (string) preg_split('/\s+/', $candidate)[0];
            }
        }

        $urls = [];

        foreach ($candidates as $candidate) {
            if (!Url::isFetchable($candidate) || in_array($candidate, $urls, true)) {
                continue;
            }

            $urls[] = $candidate;
        }

        return $urls;
    }

    /**
     * Read a single attribute out of a tag, quoted or not.
     */
    public static function attribute(string $html, string $name): ?string
    {
        $pattern = '/\b' . preg_quote($name, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/i';

        if (!preg_match($pattern, $html, $match)) {
            return null;
        }

        foreach ([1, 2, 3] as $group) {
            if (isset($match[$group]) && $match[$group] !== '') {
                return $match[$group];
            }
        }

        return '';
    }
}
