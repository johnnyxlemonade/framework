<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Response;

final class HtmlMinifier
{
    /**
     * Tags where whitespace and content must be preserved exactly.
     *
     * @var list<string>
     */
    private const PRESERVED_TAGS = [
        'pre',
        'textarea',
        'script',
        'style',
        'template',
    ];

    public function minify(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $preservedBlocks = [];

        $html = $this->preserveBlocks($html, $preservedBlocks);
        $html = $this->removeSafeComments($html);
        $html = $this->normalizeMultilineTags($html);
        $html = $this->trimWhitespaceBeforeTagEnd($html);
        $html = $this->collapseWhitespace($html);

        return trim($this->restoreBlocks($html, $preservedBlocks));
    }

    /**
     * Removes regular HTML comments while preserving conditional comments.
     */
    private function removeSafeComments(string $html): string
    {
        return $this->pregReplace(
            '/<!--(?!\[if\b|!).*?-->/is',
            '',
            $html,
        );
    }

    /**
     * Converts multiline tag declarations into a single-line form.
     *
     * Example:
     * <a
     *     href="/"
     *     class="btn">
     *
     * becomes:
     * <a href="/" class="btn">
     */
    private function normalizeMultilineTags(string $html): string
    {
        do {
            $normalizedHtml = $this->pregReplace(
                '/<([a-z0-9]+)([^>]*?)\s*[\r\n]+([^>]*)>/iu',
                '<$1$2 $3>',
                $html,
            );

            $changed = $normalizedHtml !== $html;
            $html = $normalizedHtml;
        } while ($changed);

        return $html;
    }

    /**
     * Removes unnecessary whitespace before the closing bracket of an HTML tag.
     *
     * Example:
     * <a class="btn" >
     *
     * becomes:
     * <a class="btn">
     */
    private function trimWhitespaceBeforeTagEnd(string $html): string
    {
        return $this->pregReplace(
            '/<([^<>]*?)\s+>/s',
            '<$1>',
            $html,
        );
    }

    /**
     * Collapses unnecessary whitespace outside preserved blocks.
     */
    private function collapseWhitespace(string $html): string
    {
        return $this->pregReplace(
            [
                '/\>[^\S ]+/s',
                '/[^\S ]+\</s',
                '/([\t ])+/s',
                '/^([\t ])+/m',
                '/([\t ])+$/m',
                '/[\r\n]+([\t ]?[\r\n]+)+/s',
                '/\>[\r\n\t ]+\</s',
            ],
            [
                '>',
                '<',
                ' ',
                '',
                '',
                "\n",
                '><',
            ],
            $html,
        );
    }

    /**
     * @param array<string, string> $blocks
     */
    private function preserveBlocks(string $html, array &$blocks): string
    {
        foreach (self::PRESERVED_TAGS as $tag) {
            $html = $this->pregReplaceCallback(
                sprintf('/<%1$s\b[^>]*>.*?<\/%1$s>/is', preg_quote($tag, '/')),
                /**
                 * @param array<int|string, string> $matches
                 */
                static function (array $matches) use (&$blocks): string {
                    $key = "\x1A"
                        . 'LEMONADE_HTML_BLOCK_'
                        . count($blocks)
                        . '_'
                        . md5($matches[0])
                        . "\x1A";

                    $blocks[$key] = $matches[0];

                    return $key;
                },
                $html,
            );
        }

        return $html;
    }

    /**
     * @param array<string, string> $blocks
     */
    private function restoreBlocks(string $html, array $blocks): string
    {
        if ($blocks === []) {
            return $html;
        }

        return strtr($html, $blocks);
    }

    /**
     * @param string|list<string> $pattern
     * @param string|list<string> $replacement
     */
    private function pregReplace(string|array $pattern, string|array $replacement, string $subject): string
    {
        $result = preg_replace($pattern, $replacement, $subject);

        if ($result === null) {
            return $subject;
        }

        return $result;
    }

    /**
     * @param callable(array<int|string, string>): string $callback
     */
    private function pregReplaceCallback(string $pattern, callable $callback, string $subject): string
    {
        $result = preg_replace_callback($pattern, $callback, $subject);

        if ($result === null) {
            return $subject;
        }

        return $result;
    }
}
