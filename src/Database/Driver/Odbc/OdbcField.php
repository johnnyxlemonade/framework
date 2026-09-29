<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

final readonly class OdbcField
{
    public function __construct(
        private string $name,
        private string $type,
        private int $maxLength,
        private bool $primaryKey = false,
        private mixed $default = null,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function maxLength(): int
    {
        return $this->maxLength;
    }

    public function primaryKey(): bool
    {
        return $this->primaryKey;
    }

    public function default(): mixed
    {
        return $this->default;
    }

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
