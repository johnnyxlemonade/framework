<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

final class SessionDatabaseConfig
{
    public function __construct(
        public readonly string $table,
    ) {}
}
