<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

final readonly class FrameworkConfig
{
    /** @param list<class-string> $providers */
    public function __construct(
        public array $providers,
    ) {}
}
