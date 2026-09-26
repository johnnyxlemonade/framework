<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

final readonly class ProvidersConfig
{
    /** @param list<class-string> $providers */
    public function __construct(
        public array $providers,
    ) {}
}
