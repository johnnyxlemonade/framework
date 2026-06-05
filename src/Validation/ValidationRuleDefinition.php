<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation;

use InvalidArgumentException;

final class ValidationRuleDefinition
{
    private function __construct(
        private readonly string $name,
        private readonly ?string $param = null,
        private readonly ?string $message = null,
    ) {}

    public static function create(string $name, ?string $param = null, ?string $message = null): self
    {
        return new self(
            self::normalizeName($name),
            self::normalizeOptionalString($param),
            self::normalizeOptionalString($message),
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function param(): ?string
    {
        return $this->param;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function withMessage(string $message): self
    {
        $message = self::normalizeOptionalString($message);
        if ($message === null) {
            throw new InvalidArgumentException('Validation rule message cannot be empty.');
        }

        return new self($this->name, $this->param, $message);
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Validation rule name cannot be empty.');
        }

        return $name;
    }

    private static function normalizeOptionalString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
