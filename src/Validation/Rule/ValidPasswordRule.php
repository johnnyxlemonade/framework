<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

/**
 * Accepts unmodified UTF-8 passwords between 15 and 128 Unicode characters.
 *
 * The rule deliberately imposes no character-class, repetition, or whitespace policy.
 */
final readonly class ValidPasswordRule implements ValidationRuleInterface
{
    private const int MIN_LENGTH = 15;

    private const int MAX_LENGTH = 128;

    /**
     * Validates only string input and counts Unicode characters without trimming or normalization.
     *
     * @param array<string, mixed> $data
     */
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return false;
        }

        $length = mb_strlen($value, 'UTF-8');

        return $length >= self::MIN_LENGTH && $length <= self::MAX_LENGTH;
    }
}
