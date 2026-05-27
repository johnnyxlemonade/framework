<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

final class MetaTag extends AbstractTag
{
    protected function template(): string
    {
        return '<meta name="%s" content="%s">';
    }
}
