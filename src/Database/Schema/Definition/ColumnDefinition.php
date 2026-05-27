<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Definition;

use Lemonade\Framework\Database\Schema\Enum\ColumnType;

final class ColumnDefinition
{
    public function __construct(
        private readonly string $name,
        private readonly ColumnType|string $type,
        private readonly int|string|null $length = null,
        private readonly bool $unsigned = false,
        private readonly bool $nullable = false,
        private readonly bool $hasDefault = false,
        private readonly mixed $default = null,
        private readonly bool $autoIncrement = false,
        private readonly ?string $comment = null,
        private readonly ?string $after = null,
        private readonly bool $first = false,
        private readonly ?string $renameTo = null,
        private readonly ?string $literal = null,
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Column name cannot be empty.');
        }
    }

    public static function raw(string $definition): self
    {
        return new self(
            name: $definition,
            type: '',
            literal: $definition,
        );
    }

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

    public static function string(string $name, int $length = 255): self
    {
        return new self($name, ColumnType::String, $length);
    }

    public static function integer(string $name): self
    {
        return new self($name, ColumnType::Integer);
    }

    public static function datetime(string $name): self
    {
        return new self($name, ColumnType::DateTime);
    }

    public static function text(string $name): self
    {
        return new self($name, ColumnType::Text);
    }

    public function nullable(bool $nullable = true): self
    {
        return $this->with(nullable: $nullable);
    }

    public function unsigned(bool $unsigned = true): self
    {
        return $this->with(unsigned: $unsigned);
    }

    public function default(mixed $value): self
    {
        return $this->with(hasDefault: true, default: $value);
    }

    public function defaultExpression(string $expression): self
    {
        return $this->default(SqlExpression::raw($expression));
    }

    public function autoIncrement(bool $autoIncrement = true): self
    {
        return $this->with(autoIncrement: $autoIncrement);
    }

    public function comment(?string $comment): self
    {
        return $this->with(comment: $comment, replaceComment: true);
    }

    public function after(?string $column): self
    {
        return $this->with(after: $column, replaceAfter: true, first: false);
    }

    public function first(bool $first = true): self
    {
        return $this->with(first: $first, after: null, replaceAfter: true);
    }

    public function renameTo(?string $name): self
    {
        return $this->with(renameTo: $name, replaceRenameTo: true);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): ColumnType|string
    {
        return $this->type;
    }

    public function typeValue(): string
    {
        return $this->type instanceof ColumnType
            ? $this->type->value
            : strtoupper($this->type);
    }

    public function length(): int|string|null
    {
        return $this->length;
    }

    public function isUnsigned(): bool
    {
        return $this->unsigned;
    }

    public function nullableFlag(): bool
    {
        return $this->nullable;
    }

    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    public function defaultValue(): mixed
    {
        return $this->default;
    }

    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    public function commentText(): ?string
    {
        return $this->comment;
    }

    public function afterColumn(): ?string
    {
        return $this->after;
    }

    public function firstPosition(): bool
    {
        return $this->first;
    }

    public function renameTarget(): ?string
    {
        return $this->renameTo;
    }

    public function literal(): ?string
    {
        return $this->literal;
    }

    public function isLiteral(): bool
    {
        return $this->literal !== null;
    }

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
