<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Config;

final class MetaConfig
{
    public function __construct(
        public readonly string $websiteName,
        public readonly string $charset,
        public readonly string $viewport,
        public readonly string $rating,
        public readonly string $titleSeparator,
    ) {}
}
