<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

final class LinkTag extends AbstractTag
{
    protected function template(): string
    {
        return '<link rel="%s" href="%s">';
    }
}
