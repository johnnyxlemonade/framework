<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Blueprint;

use Lemonade\Framework\Database\Schema\Definition\ColumnDefinition;
use Lemonade\Framework\Database\Schema\Definition\SqlExpression;
use Lemonade\Framework\Database\Schema\Enum\ColumnType;

/**
 * Collects modifiers for one column while its owning table blueprint is being defined.
 */
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

    /**
     * Initializes this ColumnBlueprint with the collaborators and state required by its database contract.
     */
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

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public static function raw(string $definition): self
    {
        $column = new self($definition, '');
        $column->literal = $definition;

        return $column;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function unsigned(bool $unsigned = true): self
    {
        $this->unsigned = $unsigned;

        return $this;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function nullable(bool $nullable = true): self
    {
        $this->nullable = $nullable;

        return $this;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function default(mixed $value): self
    {
        $this->hasDefault = true;
        $this->default = $value;

        return $this;
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
        $this->autoIncrement = $autoIncrement;

        return $this;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function comment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function after(string $column): self
    {
        $this->after = $column;
        $this->first = false;

        return $this;
    }

    /**
     * Retrieves the requested value without changing the current database state.
     */
    public function first(): self
    {
        $this->first = true;
        $this->after = null;

        return $this;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function renameTo(string $name): self
    {
        $this->renameTo = $name;

        return $this;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function primary(?string $name = null): self
    {
        $this->table?->primary($this->name, $name);

        return $this;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function index(?string $name = null): self
    {
        $this->table?->index($this->name, $name);

        return $this;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function unique(?string $name = null): self
    {
        $this->table?->unique($this->name, $name);

        return $this;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function fulltext(?string $name = null): self
    {
        $this->table?->fulltext($this->name, $name);

        return $this;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
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
