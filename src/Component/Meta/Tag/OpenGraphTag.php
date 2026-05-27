<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Tag;

final class OpenGraphTag extends AbstractTag
{
    protected function template(): string
    {
        return '<meta property="%s" content="%s">';
    }
}
