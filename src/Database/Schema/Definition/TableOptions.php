<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Definition;

/**
 * Provides the table options contract for the database subsystem.
 */
final readonly class TableOptions
{
    /**
     * @param array<string, int|string|bool|SqlExpression|null> $extra
     */
    public function __construct(
        private ?string $engine = null,
        private ?string $charset = null,
        private ?string $collation = null,
        private ?string $comment = null,
        private array $extra = [],
    ) {
    }

    /**
     * Builds the requested database value while preserving its framework contract.
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function engine(?string $engine): self
    {
        return new self($engine, $this->charset, $this->collation, $this->comment, $this->extra);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function charset(?string $charset): self
    {
        return new self($this->engine, $charset, $this->collation, $this->comment, $this->extra);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function collation(?string $collation): self
    {
        return new self($this->engine, $this->charset, $collation, $this->comment, $this->extra);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function comment(?string $comment): self
    {
        return new self($this->engine, $this->charset, $this->collation, $comment, $this->extra);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function option(string $name, int|string|bool|SqlExpression|null $value): self
    {
        $extra = $this->extra;
        $extra[$name] = $value;

        return new self($this->engine, $this->charset, $this->collation, $this->comment, $extra);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function engineName(): ?string
    {
        return $this->engine;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function charsetName(): ?string
    {
        return $this->charset;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function collationName(): ?string
    {
        return $this->collation;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function commentText(): ?string
    {
        return $this->comment;
    }

    /**
     * @return array<string, int|string|bool|SqlExpression|null>
     */
    public function extra(): array
    {
        return $this->extra;
    }
}
