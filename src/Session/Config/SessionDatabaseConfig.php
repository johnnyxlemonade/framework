<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

final readonly class SessionDatabaseConfig
{
    public function __construct(
        public string $table,
    ) {
    }
}
