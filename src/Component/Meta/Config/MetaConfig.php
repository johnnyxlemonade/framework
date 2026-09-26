<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Config;

final readonly class MetaConfig
{
    public function __construct(
        public string $websiteName,
        public string $charset,
        public string $viewport,
        public string $rating,
        public string $titleSeparator,
    ) {}
}
