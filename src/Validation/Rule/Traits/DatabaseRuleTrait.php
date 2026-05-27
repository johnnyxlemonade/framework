<?php

declare(strict_types=1);

namespace Lemonade\Framework\Validation\Rule\Traits;

use Lemonade\Framework\Database\Database;

trait DatabaseRuleTrait
{
    protected function database(): ?Database
    {
        $db = service(Database::class);

        return $db instanceof Database ? $db : null;
    }

    protected function isSafeIdentifier(string $value): bool
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value) === 1;
    }
}
