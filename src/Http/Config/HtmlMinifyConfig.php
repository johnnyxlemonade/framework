<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

final class HtmlMinifyConfig
{
    public function __construct(
        public readonly bool $enabled,
    ) {}
}
