<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

final class RequiredWithRule implements ValidationRuleInterface
{
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        $fields = array_filter(
            array_map('trim', explode(',', (string) $param)),
            static fn(string $field): bool => $field !== '',
        );
        if ($fields === []) {
            return false;
        }

        $shouldRequire = false;
        foreach ($fields as $field) {
            $other = $data[$field] ?? null;
            if (!($other === null || (is_string($other) && trim($other) === ''))) {
                $shouldRequire = true;
                break;
            }
        }

        if (!$shouldRequire) {
            return true;
        }

        return !($value === null || (is_string($value) && trim($value) === ''));
    }
}
