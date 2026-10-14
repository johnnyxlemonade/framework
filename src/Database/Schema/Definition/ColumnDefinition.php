<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Definition;

use Lemonade\Framework\Database\Schema\Enum\ColumnType;

/**
 * Provides the column definition contract for the database subsystem.
 */
final readonly class ColumnDefinition
{
    /**
     * Initializes this ColumnDefinition with the collaborators and state required by its database contract.
     */
    public function __construct(
        private string $name,
        private ColumnType|string $type,
        private int|string|null $length = null,
        private bool $unsigned = false,
        private bool $nullable = false,
        private bool $hasDefault = false,
        private mixed $default = null,
        private bool $autoIncrement = false,
        private ?string $comment = null,
        private ?string $after = null,
        private bool $first = false,
        private ?string $renameTo = null,
        private ?string $literal = null,
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Column name cannot be empty.');
        }
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public static function raw(string $definition): self
    {
        return new self(
            name: $definition,
            type: '',
            literal: $definition,
        );
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public static function id(string $name = 'id'): self
    {
        return new self(
            name: $name,
            type: ColumnType::BigInteger,
            unsigned: true,
            nullable: false,
            autoIncrement: true,
        );
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public static function string(string $name, int $length = 255): self
    {
        return new self($name, ColumnType::String, $length);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public static function integer(string $name): self
    {
        return new self($name, ColumnType::Integer);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public static function datetime(string $name): self
    {
        return new self($name, ColumnType::DateTime);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public static function text(string $name): self
    {
        return new self($name, ColumnType::Text);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function nullable(bool $nullable = true): self
    {
        return $this->with(nullable: $nullable);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function unsigned(bool $unsigned = true): self
    {
        return $this->with(unsigned: $unsigned);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function default(mixed $value): self
    {
        return $this->with(hasDefault: true, default: $value);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function defaultExpression(string $expression): self
    {
        return $this->default(SqlExpression::raw($expression));
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function autoIncrement(bool $autoIncrement = true): self
    {
        return $this->with(autoIncrement: $autoIncrement);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function comment(?string $comment): self
    {
        return $this->with(comment: $comment, replaceComment: true);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function after(?string $column): self
    {
        return $this->with(after: $column, replaceAfter: true, first: false);
    }

    /**
     * Retrieves the requested value without changing the current database state.
     */
    public function first(bool $first = true): self
    {
        return $this->with(first: $first, after: null, replaceAfter: true);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function renameTo(?string $name): self
    {
        return $this->with(renameTo: $name, replaceRenameTo: true);
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
    public function type(): ColumnType|string
    {
        return $this->type;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function typeValue(): string
    {
        return $this->type instanceof ColumnType
            ? $this->type->value
            : strtoupper($this->type);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function length(): int|string|null
    {
        return $this->length;
    }

    /**
     * Reports whether the requested condition holds for the current database state.
     */
    public function isUnsigned(): bool
    {
        return $this->unsigned;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function nullableFlag(): bool
    {
        return $this->nullable;
    }

    /**
     * Reports whether the requested condition holds for the current database state.
     */
    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function defaultValue(): mixed
    {
        return $this->default;
    }

    /**
     * Reports whether the requested condition holds for the current database state.
     */
    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function commentText(): ?string
    {
        return $this->comment;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function afterColumn(): ?string
    {
        return $this->after;
    }

    /**
     * Retrieves the requested value without changing the current database state.
     */
    public function firstPosition(): bool
    {
        return $this->first;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function renameTarget(): ?string
    {
        return $this->renameTo;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function literal(): ?string
    {
        return $this->literal;
    }

    /**
     * Reports whether the requested condition holds for the current database state.
     */
    public function isLiteral(): bool
    {
        return $this->literal !== null;
    }

    /**
     * Updates the query or object state used by subsequent database operations.
     */
    private function with(
        ColumnType|string|null $type = null,
        int|string|null $length = null,
        ?bool $unsigned = null,
        ?bool $nullable = null,
        ?bool $hasDefault = null,
        mixed $default = null,
        ?bool $autoIncrement = null,
        ?string $comment = null,
        bool $replaceComment = false,
        ?string $after = null,
        bool $replaceAfter = false,
        ?bool $first = null,
        ?string $renameTo = null,
        bool $replaceRenameTo = false,
    ): self {
        return new self(
            name: $this->name,
            type: $type ?? $this->type,
            length: $length ?? $this->length,
            unsigned: $unsigned ?? $this->unsigned,
            nullable: $nullable ?? $this->nullable,
            hasDefault: $hasDefault ?? $this->hasDefault,
            default: ($hasDefault === true) ? $default : $this->default,
            autoIncrement: $autoIncrement ?? $this->autoIncrement,
            comment: $replaceComment ? $comment : ($comment ?? $this->comment),
            after: $replaceAfter ? $after : ($after ?? $this->after),
            first: $first ?? $this->first,
            renameTo: $replaceRenameTo ? $renameTo : ($renameTo ?? $this->renameTo),
            literal: $this->literal,
        );
    }
}
