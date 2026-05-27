<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

final class AlternateLinkTag extends AbstractTag
{
    protected function template(): string
    {
        return '<link rel="alternate" hreflang="%s" href="%s">';
    }
}
