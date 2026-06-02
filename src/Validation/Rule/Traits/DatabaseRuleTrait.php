<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule\Traits;

trait DatabaseRuleTrait
{
    protected function isSafeIdentifier(string $value): bool
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value) === 1;
    }
}
