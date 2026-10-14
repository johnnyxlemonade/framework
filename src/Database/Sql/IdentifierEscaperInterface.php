<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Sql;

/**
 * Escapes one SQL identifier part according to a driver's identifier quoting rules.
 */
interface IdentifierEscaperInterface
{
    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function identifier(string $identifier): string;

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function table(string $table): string;
}
