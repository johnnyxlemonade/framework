<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

/**
 * Carries the table selection for database-backed session storage
 */
final readonly class SessionDatabaseConfig
{
    /**
     * Defines the table that stores serialized session payloads
     */
    public function __construct(
        private string $table,
    ) {
    }

    /**
     * Returns the table that stores serialized session payloads.
     */
    public function table(): string
    {
        return $this->table;
    }
}
