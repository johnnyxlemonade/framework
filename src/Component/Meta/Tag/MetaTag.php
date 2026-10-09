<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

/**
 * Renders a name-based HTML metadata declaration.
 */
final readonly class MetaTag extends AbstractTag
{
    protected function template(): string
    {
        return '<meta name="%s" content="%s">';
    }
}
