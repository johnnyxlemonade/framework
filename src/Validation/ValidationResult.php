<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation;

final class ValidationResult
{
    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $validated
     */
    public function __construct(
        private readonly bool $valid,
        private readonly array $errors,
        private readonly array $validated,
    ) {}

    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->validated;
    }

    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }
}
