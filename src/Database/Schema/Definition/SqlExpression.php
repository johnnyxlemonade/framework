<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Definition;

final class SqlExpression
{
    public function __construct(
        private readonly string $sql,
    ) {
        if (trim($sql) === '') {
            throw new \InvalidArgumentException('SQL expression cannot be empty.');
        }
    }

    public static function raw(string $sql): self
    {
        return new self($sql);
    }

    public function sql(): string
    {
        return $this->sql;
    }
}
