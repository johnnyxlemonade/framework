<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation;

final class ValidationResult
{
    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $validated
     * @param array<string, list<string>> $failedRules
     * @param array<string, mixed> $input
     */
    public function __construct(
        private readonly bool $valid,
        private readonly array $errors,
        private readonly array $validated,
        private readonly array $failedRules = [],
        private readonly array $input = [],
    ) {}

    public function isValid(): bool
    {
        return $this->valid;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /** @return array<string, list<string>> */
    public function failedRules(): array
    {
        return $this->failedRules;
    }

    public function failedOnlyOnRule(string $field, string $rule): bool
    {
        $failedRules = $this->failedRules[$field] ?? [];

        return count($failedRules) === 1 && $failedRules[0] === $rule;
    }

    public function getValueIfFailedOnlyOnRule(string $field, string $rule): mixed
    {
        if (!$this->failedOnlyOnRule($field, $rule)) {
            return null;
        }

        return $this->input[$field] ?? null;
    }

    /**
     * @param array<string, mixed> $extraData
     * @return array<string, mixed>
     */
    public function toArray(array $extraData = []): array
    {
        $result = [
            'valid' => $this->valid ? $this->validated : [],
            'input' => $this->valid ? [] : $this->input,
            'errors' => $this->errors,
            'failed_rules' => $this->failedRules,
        ];

        return array_replace($result, $extraData);
    }
}
