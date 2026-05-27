<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule;

interface ValidationRuleInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function validate(mixed $value, ?string $param, array $data): bool;
}
