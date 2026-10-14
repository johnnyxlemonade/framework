<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Definition;

/**
 * Provides the sql expression contract for the database subsystem.
 */
final readonly class SqlExpression
{
    /**
     * Initializes this SqlExpression with the collaborators and state required by its database contract.
     */
    public function __construct(
        private string $sql,
    ) {
        if (trim($sql) === '') {
            throw new \InvalidArgumentException('SQL expression cannot be empty.');
        }
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public static function raw(string $sql): self
    {
        return new self($sql);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function sql(): string
    {
        return $this->sql;
    }
}
