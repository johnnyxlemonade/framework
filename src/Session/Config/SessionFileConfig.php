<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

final class SessionFileConfig
{
    public function __construct(
        public readonly string $path,
    ) {}
}
