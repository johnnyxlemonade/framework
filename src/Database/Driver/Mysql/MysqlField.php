<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

/**
 * Describes one MySQL result column in framework and legacy-compatible representations.
 */
final readonly class MysqlField
{
    /**
     * Captures MySQL field metadata as reported by the native result handle.
     */
    public function __construct(
        private string $name,
        private string|int $type,
        private int $maxLength,
        private bool $primaryKey,
        private mixed $default,
    ) {
    }

    /**
     * Returns the column name as exposed by MySQL.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the normalized legacy type name or an unrecognized native type constant.
     */
    public function type(): string|int
    {
        return $this->type;
    }

    /**
     * Returns the maximum observed or declared field length reported by MySQL.
     */
    public function maxLength(): int
    {
        return $this->maxLength;
    }

    /**
     * Indicates whether MySQL metadata marks this field as part of the primary key.
     */
    public function primaryKey(): bool
    {
        return $this->primaryKey;
    }

    /**
     * Returns the default value reported by MySQL metadata when available.
     */
    public function default(): mixed
    {
        return $this->default;
    }

    /**
     * Converts metadata to the legacy object shape expected by compatible consumers.
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
     * Converts metadata to the legacy associative array shape expected by compatible consumers.
     *
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
