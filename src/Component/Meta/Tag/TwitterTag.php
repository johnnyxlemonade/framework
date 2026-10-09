<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

/**
 * Renders a Twitter Card metadata declaration.
 */
final readonly class TwitterTag extends AbstractTag
{
    protected function template(): string
    {
        return '<meta name="%s" content="%s">';
    }
}
