<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

final readonly class HtmlMinifyConfig
{
    public function __construct(
        public bool $enabled,
    ) {}
}
