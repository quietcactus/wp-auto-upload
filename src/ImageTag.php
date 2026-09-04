<?php
/**
 * A single <img>/<source> tag found in post content.
 *
 * @package ExternalImageImporter
 */

declare(strict_types=1);

namespace ExternalImageImporter;

defined('ABSPATH') || exit;

final class ImageTag
{
    /**
     * Working copy of the tag, rewritten as images are imported.
     */
    private string $rewritten;

    /**
     * @param string            $html The tag exactly as it appears in the content.
     * @param array<int,string> $urls Raw URLs referenced by src/srcset, as written.
     * @param string|null       $alt  Value of the alt attribute, if present.
     */
    public function __construct(
        public readonly string $html,
        public readonly array $urls,
        public readonly ?string $alt = null
    ) {
        $this->rewritten = $html;
    }

    /**
     * Swap one URL for another inside this tag.
     */
    public function replaceUrl(string $from, string $to): void
    {
        $this->rewritten = str_replace($from, $to, $this->rewritten);
    }

    /**
     * Set (or add) the alt attribute of this tag.
     */
    public function setAlt(string $alt): void
    {
        // <source> elements have no alt attribute.
        if (!preg_match('/^<img\b/i', $this->rewritten)) {
            return;
        }

        $value   = self::quoteReplacement(esc_attr($alt));
        $pattern = '/\balt\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i';

        if (preg_match($pattern, $this->rewritten)) {
            $this->rewritten = (string) preg_replace($pattern, 'alt="' . $value . '"', $this->rewritten, 1);

            return;
        }

        $this->rewritten = (string) preg_replace('/^<img\b/i', '<img alt="' . $value . '"', $this->rewritten, 1);
    }

    /**
     * The rewritten tag.
     */
    public function rewritten(): string
    {
        return $this->rewritten;
    }

    /**
     * True when rewriting actually changed something.
     */
    public function isModified(): bool
    {
        return $this->rewritten !== $this->html;
    }

    /**
     * Escape a literal string for use as a preg_replace() replacement.
     *
     * Backslashes must be escaped before dollars, otherwise the backslash we
     * add in front of a "$" gets escaped a second time.
     */
    private static function quoteReplacement(string $value): string
    {
        return str_replace('$', '\\$', str_replace('\\', '\\\\', $value));
    }
}
