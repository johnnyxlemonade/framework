<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Definition;

final class TableOptions
{
    /**
     * @param array<string, int|string|bool|SqlExpression|null> $extra
     */
    public function __construct(
        private readonly ?string $engine = null,
        private readonly ?string $charset = null,
        private readonly ?string $collation = null,
        private readonly ?string $comment = null,
        private readonly array $extra = [],
    ) {}

    public static function make(): self
    {
        return new self();
    }

    public function engine(?string $engine): self
    {
        return new self($engine, $this->charset, $this->collation, $this->comment, $this->extra);
    }

    public function charset(?string $charset): self
    {
        return new self($this->engine, $charset, $this->collation, $this->comment, $this->extra);
    }

    public function collation(?string $collation): self
    {
        return new self($this->engine, $this->charset, $collation, $this->comment, $this->extra);
    }

    public function comment(?string $comment): self
    {
        return new self($this->engine, $this->charset, $this->collation, $comment, $this->extra);
    }

    public function option(string $name, int|string|bool|SqlExpression|null $value): self
    {
        $extra = $this->extra;
        $extra[$name] = $value;

        return new self($this->engine, $this->charset, $this->collation, $this->comment, $extra);
    }

    public function engineName(): ?string
    {
        return $this->engine;
    }

    public function charsetName(): ?string
    {
        return $this->charset;
    }

    public function collationName(): ?string
    {
        return $this->collation;
    }

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
