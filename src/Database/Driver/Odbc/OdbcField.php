<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

/**
 * Describes one ODBC result column as exposed by the ODBC extension metadata API.
 */
final readonly class OdbcField
{
    /**
     * Initializes this OdbcField with the collaborators and state required by its database contract.
     */
    public function __construct(
        private string $name,
        private string $type,
        private int $maxLength,
        private bool $primaryKey = false,
        private mixed $default = null,
    ) {
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function maxLength(): int
    {
        return $this->maxLength;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function primaryKey(): bool
    {
        return $this->primaryKey;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function default(): mixed
    {
        return $this->default;
    }

    /**
     * Converts the current database value into the representation required by its consumer.
     */
    public function toObject(): object
    {
        return (object) [
            'name' => $this->name(),
            'type' => $this->type(),
            'max_length' => $this->maxLength(),
            'primary_key' => $this->primaryKey(),
            'default' => $this->default(),
        ];
    }
}
