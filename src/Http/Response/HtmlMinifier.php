<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Response;

final class HtmlMinifier
{
    /**
     * Tags whose raw text content must be preserved exactly.
     *
     * @var list<string>
     */
    private const PRESERVED_TAGS = [
        'script',
        'style',
    ];

    /**
     * Tags whose text content can be made single-line with character references.
     *
     * @var list<string>
     */
    private const TEXT_BLOCK_TAGS = [
        'pre',
        'textarea',
    ];

    public function minify(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $preservedBlocks = [];

        $html = $this->preserveBlocks($html, $preservedBlocks);
        $html = $this->removeSafeComments($html);
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
     * Collapses unnecessary whitespace outside preserved blocks.
     */
    private function collapseWhitespace(string $html): string
    {
        return $this->pregReplace(
            [
                '/<(?![\/!?])([^>]*)>\s*[\r\n]\s*/s',
                '/\s*[\r\n]\s*<\//s',
                '/[\t\r\n ]+/s',
                '/<([^>]*)\s+>/s',
                '/>\s+</s',
            ],
            [
                '<$1>',
                '</',
                ' ',
                '<$1>',
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
                sprintf('/<%1$s\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*?>.*?<\/%1$s\s*>/is', preg_quote($tag, '/')),
                /**
                 * @param array<int|string, string> $matches
                 */
                function (array $matches) use (&$blocks): string {
                    return $this->preserve($matches[0], $blocks);
                },
                $html,
            );
        }

        foreach (self::TEXT_BLOCK_TAGS as $tag) {
            $html = $this->encodeTextBlockWhitespace($html, $tag);
        }

        $html = $this->pregReplaceCallback(
            '/<!--(?:\[if\b|!).*?-->/is',
            function (array $matches) use (&$blocks): string {
                return $this->preserve($matches[0], $blocks);
            },
            $html,
        );

        return $this->pregReplaceCallback(
            '/"[^"]*"|\'[^\']*\'/s',
            function (array $matches) use (&$blocks): string {
                return $this->preserve($matches[0], $blocks);
            },
            $html,
        );
    }

    /**
     * Replaces whitespace that the general HTML minification would otherwise
     * collapse with character references. HTML parsers decode these references
     * back to the original text-node characters.
     */
    private function encodeTextBlockWhitespace(string $html, string $tag): string
    {
        return $this->pregReplaceCallback(
            sprintf(
                '/(<%1$s\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*?>)(.*?)(<\/%1$s\s*>)/is',
                preg_quote($tag, '/'),
            ),
            function (array $matches): string {
                $content = $this->pregReplace('/\A(?:\r\n|\r|\n)/', '', $matches[2]);
                $content = str_replace(
                    ["\r\n", "\r", "\n", "\t"],
                    ['&#10;', '&#10;', '&#10;', '&#9;'],
                    $content,
                );
                $content = $this->pregReplaceCallback(
                    '/ {2,}/',
                    static function (array $spaceRun): string {
                        return ' ' . str_repeat('&#32;', strlen($spaceRun[0]) - 1);
                    },
                    $content,
                );

                return $matches[1] . $content . $matches[3];
            },
            $html,
        );
    }

    /**
     * @param array<string, string> $blocks
     */
    private function preserve(string $block, array &$blocks): string
    {
        $key = "\x1A"
            . 'LEMONADE_HTML_BLOCK_'
            . count($blocks)
            . '_'
            . md5($block)
            . "\x1A";

        $blocks[$key] = $block;

        return $key;
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
