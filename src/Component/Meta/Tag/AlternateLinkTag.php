<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

/**
 * Renders an alternate-language link tag from a language code and URL.
 */
final readonly class AlternateLinkTag extends AbstractTag
{
    protected function template(): string
    {
        return '<link rel="alternate" hreflang="%s" href="%s">';
    }
}
