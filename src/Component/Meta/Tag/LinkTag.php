<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

/**
 * Renders a relation link tag from a relation name and URL.
 */
final readonly class LinkTag extends AbstractTag
{
    protected function template(): string
    {
        return '<link rel="%s" href="%s">';
    }
}
