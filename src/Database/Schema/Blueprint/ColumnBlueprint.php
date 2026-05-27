<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Blueprint;

use Lemonade\Framework\Database\Schema\Definition\ColumnDefinition;
use Lemonade\Framework\Database\Schema\Definition\SqlExpression;
use Lemonade\Framework\Database\Schema\Enum\ColumnType;

final class ColumnBlueprint
{
    private bool $unsigned = false;
    private bool $nullable = false;
    private bool $hasDefault = false;
    private mixed $default = null;
    private bool $autoIncrement = false;
    private ?string $comment = null;
    private ?string $after = null;
    private bool $first = false;
    private ?string $renameTo = null;
    private ?string $literal = null;

    public function __construct(
        private readonly string $name,
        private readonly ColumnType|string $type,
        private readonly int|string|null $length = null,
        private readonly ?TableBlueprint $table = null,
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Column name cannot be empty.');
        }
    }

    public static function raw(string $definition): self
    {
        $column = new self($definition, '');
        $column->literal = $definition;

        return $column;
    }

    public function unsigned(bool $unsigned = true): self
    {
        $this->unsigned = $unsigned;

        return $this;
    }

    public function nullable(bool $nullable = true): self
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->hasDefault = true;
        $this->default = $value;

        return $this;
    }

    public function defaultExpression(string $expression): self
    {
        return $this->default(SqlExpression::raw($expression));
    }

    public function autoIncrement(bool $autoIncrement = true): self
    {
        $this->autoIncrement = $autoIncrement;

        return $this;
    }

    public function comment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function after(string $column): self
    {
        $this->after = $column;
        $this->first = false;

        return $this;
    }

    public function first(): self
    {
        $this->first = true;
        $this->after = null;

        return $this;
    }

    public function renameTo(string $name): self
    {
        $this->renameTo = $name;

        return $this;
    }

    public function primary(?string $name = null): self
    {
        $this->table?->primary($this->name, $name);

        return $this;
    }

    public function index(?string $name = null): self
    {
        $this->table?->index($this->name, $name);

        return $this;
    }

    public function unique(?string $name = null): self
    {
        $this->table?->unique($this->name, $name);

        return $this;
    }

    public function fulltext(?string $name = null): self
    {
        $this->table?->fulltext($this->name, $name);

        return $this;
    }

    public function definition(): ColumnDefinition
    {
        if ($this->literal !== null) {
            return ColumnDefinition::raw($this->literal);
        }

        return new ColumnDefinition(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            unsigned: $this->unsigned,
            nullable: $this->nullable,
            hasDefault: $this->hasDefault,
            default: $this->default,
            autoIncrement: $this->autoIncrement,
            comment: $this->comment,
            after: $this->after,
            first: $this->first,
            renameTo: $this->renameTo,
        );
    }
}
