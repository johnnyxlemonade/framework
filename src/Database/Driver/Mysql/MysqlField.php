<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

final class MysqlField
{
    public function __construct(
        private readonly string $name,
        private readonly string|int $type,
        private readonly int $maxLength,
        private readonly bool $primaryKey,
        private readonly mixed $default,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string|int
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

    /**
     * CI-compatible shape.
     */
    public function toObject(): object
    {
        return (object) [
            'name' => $this->name,
            'type' => $this->type,
            'max_length' => $this->maxLength,
            'primary_key' => $this->primaryKey ? 1 : 0,
            'default' => $this->default,
        ];
    }

    /**
     * @return array{name:string,type:string|int,max_length:int,primary_key:int,default:mixed}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'max_length' => $this->maxLength,
            'primary_key' => $this->primaryKey ? 1 : 0,
            'default' => $this->default,
        ];
    }
}
