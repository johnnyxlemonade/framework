<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

final readonly class SessionFileConfig
{
    public function __construct(
        public string $path,
    ) {
    }
}
